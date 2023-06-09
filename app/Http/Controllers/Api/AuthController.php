<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Enums\TokenNamesEnum;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordByTokenRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\СonfirmEmailRequest;
use App\Http\Resources\Auth\LoginResource;
use App\Mail\ConfirmRegisteredEmail;
use App\Mail\ResetPasswordEmail;
use App\Models\PasswordResetTokens;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AuthController extends ApiController
{
    /**
     * @OA\Post(
     *  path="/api/auth/login",
     *  summary="Sign in",
     *  description="Login by email, password",
     *  @OA\Parameter(
     *     in="header",
     *     name="",
     *     description="Accept:application/json; Content-Type:multipart/form-data",
     *  ),
     *  @OA\RequestBody(
     *      required=true,
     *      description="Pass user credentials",
     *      @OA\JsonContent(
     *          required={"email","password"},
     *          @OA\Property(property="email", type="string", format="email", example="user1@mail.com"),
     *          @OA\Property(property="password", type="string", format="password", example="PassWord12345"),
     *      ),
     *  ),
     *  @OA\Response(
     *      response="200",
     *      description="Success response"
     *  )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return $this->response(
            LoginResource::make(
                $request->user()->createToken(TokenNamesEnum::WEB_APP_TOKEN)
            )
        );
    }

    /**
     * @OA\Post(
     *  path="/api/auth/register",
     *  summary="Sign up",
     *  description="SingUP by nickname, email, password, password_confirmation",
     *  @OA\Parameter(
     *     in="header",
     *     name="",
     *     description="Accept:application/json; Content-Type:multipart/form-data",
     *  ),
     *  @OA\RequestBody(
     *      required=true,
     *      description="Pass user credentials",
     *      @OA\JsonContent(
     *          required={"email","password"},
     *          @OA\Property(property="nickname", type="string", format="string", example="testuser1"),
     *          @OA\Property(property="email", type="string", format="email", example="user1@mail.com"),
     *          @OA\Property(property="password", type="string", format="password", example="PassWord12345"),
     *          @OA\Property(property="password_confirmation", type="string", format="password", example="PassWord12345"),
     *      ),
     *  ),
     *  @OA\Response(
     *      response="200",
     *      description="Success response"
     *  )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            /** @var User $user */
            $user = User::create([
                ...$request->validated(),
                'password' => Hash::make($request->input('password')),
                'email_token' => Str::random(64),
            ]);

            Mail::to($user->email)->send(
                new ConfirmRegisteredEmail(
                    $user->email_token
                )
            );

            DB::commit();

            return $this->response();
        } catch (\Exception $exception) {
            DB::rollBack();

            throw $exception;
        }
    }

    /**
     * @OA\Post(
     *  path="/api/auth/confirm/email",
     *  summary="Email confirmation after registration",
     *  description="Confirmation after registration by token",
     *  @OA\Parameter(
     *     in="header",
     *     name="",
     *     description="Accept:application/json; Content-Type:multipart/form-data",
     *  ),
     *  @OA\RequestBody(
     *      required=true,
     *      description="Pass user credentials",
     *      @OA\JsonContent(
     *          required={"token"},
     *          @OA\Property(property="token", type="string", format="string", example="asdfasdfasd"),
     *      ),
     *  ),
     *  @OA\Response(
     *    response="200",
     *    description="Success response"
     *  )
     * )
     */
    public function confirmEmail(СonfirmEmailRequest $request)
    {
        try {
            if (!$request->validated()) {
                throw new UnprocessableEntityHttpException(__('auth.request_not_validate'));
            }

            /** @var User $user */
            $user = User::where('email_token', $request->token)->firstOrFail();

            if ($user->email_verified_at) {
                throw new UnprocessableEntityHttpException(__('auth.email_already_confirmed'));
            }

            $user->update([
                'email_token' => '',
                'email_verified_at' => Carbon::now()
            ]);

            return $this->response();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException(__('auth.' . 'no_found_user'));
        }
    }

    /**
     * @OA\Post(
     *  path="/api/auth/reset-password",
     *  summary="Reset password",
     *  description="Reset forgot password",
     *  @OA\Parameter(
     *     in="header",
     *     name="",
     *     description="Accept:application/json;",
     *  ),
     *  @OA\RequestBody(
     *      required=true,
     *      description="Pass user credentials",
     *      @OA\JsonContent(
     *          required={"email"},
     *          @OA\Property(property="email", type="string", format="string", example="user1@mail.com"),
     *      ),
     *  ),
     *  @OA\Response(
     *    response="200",
     *    description="Success response"
     *  )
     * )
     *
     * @param ResetPasswordRequest $request
     * @return JsonResponse
     * @throws \Exception
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        try {
            $token = Str::random(64);

            PasswordResetTokens::where('email', $request->email)
                ->delete();

            PasswordResetTokens::create([
                'email' => $request->email,
                'token' => $token,
                'created_at' => Carbon::now()
            ]);

            Mail::to($request->email)->send(
                new ResetPasswordEmail($token)
            );

            DB::commit();
            return $this->response();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @OA\Post(
     *  path="/api/auth/reset-password-by-token",
     *  summary="Update Reset password by token",
     *  description="Update Reset password by token if you forgot password",
     *  @OA\Parameter(
     *     in="header",
     *     name="",
     *     description="Accept:application/json;",
     *  ),
     *  @OA\RequestBody(
     *      required=true,
     *      description="Pass user credentials",
     *      @OA\JsonContent(
     *          required={"email", "token", "password"},
     *          @OA\Property(property="email", type="string", format="string", example="user1@mail.com"),
     *          @OA\Property(property="token", type="string", format="string", example="asdfasdfasasdf"),
     *          @OA\Property(property="password", type="string", format="string", example="Password123"),
     *      ),
     *  ),
     *  @OA\Response(
     *    response="200",
     *    description="Success response"
     *  )
     * )
     *
     * @param ResetPasswordByTokenRequest $request
     * @return JsonResponse
     */
    public function resetPasswordByToken(ResetPasswordByTokenRequest $request): JsonResponse
    {
        DB::beginTransaction();
        try {
            $isResetRecordExists = PasswordResetTokens::where([
                'email' => $request->email,
                'token' => $request->token
            ])->exists();

            if (!$isResetRecordExists) {
                throw new UnprocessableEntityHttpException(__('auth.invalid-token-or-email'));
            }

            // Find user and update his password
            $user = User::where('email', $request->input('email'))->firstOrFail();
            $user->update([
                'password' => Hash::make($request->input('password'))
            ]);

            // Remove unused records of password reset in database
            PasswordResetTokens::where([
                'email' => $request->email,
                'token' => $request->token
            ])->delete();

            DB::commit();
            return $this->response();
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @OA\Post(
     *  path="/api/auth/logout",
     *  summary="Sign out",
     *  description="Logout by token",
     *  @OA\Parameter(
     *     in="header",
     *     name="",
     *     description="Accept:application/json;",
     *  ),
     *  @OA\Response(
     *    response="200",
     *    description="Success response"
     *  )
     * )
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->response();
    }
}
