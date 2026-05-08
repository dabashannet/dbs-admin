<?php
/*
 * @Author:  lvtu@dabashan.cc
 * @Date: 2026-05-08 21:42:20
 * @LastEditTime: 2026-05-08 21:42:20
 * Copyright (c) 2025 by Dabashan.cc, All Rights Reserved.
 */


namespace Dabashan\DbsAdmin\Controllers;

use Dabashan\DbsAdmin\Models\AdminUser;
use Dabashan\DbsAdmin\Traits\HasApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use HasApiResponse;

    /**
     * 用户登录
     *
     * 安全策略：
     * - 统一错误提示，防止用户名枚举攻击
     * - 限速由调用方中间件控制
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user = AdminUser::where('username', $credentials['username'])->first();

        // 统一错误提示，不区分"用户不存在"和"密码错误"
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // 记录账户锁定失败次数
            \Illuminate\Support\Facades\RateLimiter::hit(
                'lockout:' . $request->ip() . '|' . $credentials['username'],
                15 * 60
            );
            return $this->fail('凭据无效', 401);
        }

        // 登录成功，清除锁定计数
        \Illuminate\Support\Facades\RateLimiter::clear(
            'lockout:' . $request->ip() . '|' . $credentials['username']
        );

        if (!$user->status) {
            return $this->fail('帐户已被禁用', 403);
        }

        $token = $user->createToken('admin-token')->plainTextToken;

        return $this->success(['token' => $token, 'user' => $user]);
    }

    /**
     * 获取当前登录用户信息
     */
    public function me(Request $request)
    {
        $user = $request->user();
        $user->load('roles');
        $user->setRelation('permissions', $user->allPermissions());

        return $this->success($user);
    }

    /**
     * 退出登录
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success([], '退出成功');
    }

    /**
     * 修改密码
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:6|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->fail('原始密码错误', 422);
        }

        $user->update(['password' => $request->password]);

        return $this->success([], '密码修改成功');
    }
}
