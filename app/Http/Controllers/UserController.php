<?php

namespace App\Http\Controllers;

use App\Models\Labor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private function assertAdminPermission(): void
    {
        $permission = strtolower((string) (auth()->user()?->permission ?? ''));
        if (! in_array($permission, ['administrator', 'admin'], true)) {
            abort(403, 'この画面を表示する権限がありません。');
        }
    }

    private function usersPayload()
    {
        return User::query()
            ->orderBy('userID')
            ->get(['userID', 'name', 'kanji_name', 'email', 'permission', 'laborID', 'receive_info', 'signature']);
    }

    private function laborsPayload()
    {
        return Labor::query()
            ->orderBy('laborName')
            ->get(['laborID', 'laborName']);
    }

    public function page(): Response
    {
        $this->assertAdminPermission();

        return Inertia::render('Users', [
            'users' => $this->usersPayload(),
            'labors' => $this->laborsPayload(),
            'permissionOptions' => ['administrator', 'admin', 'limited', 'guest'],
        ]);
    }

    public function index(): JsonResponse
    {
        $this->assertAdminPermission();

        return response()->json($this->usersPayload());
    }

    /**
     * 既存ユーザーの更新と新規ユーザーの作成を一括保存する。
     */
    public function save(Request $request): JsonResponse
    {
        $this->assertAdminPermission();

        $validated = $request->validate([
            'users' => ['required', 'array', 'min:1'],
            'users.*.userID' => ['nullable', 'integer'],
            'users.*.name' => ['required', 'string', 'max:255'],
            'users.*.kanji_name' => ['nullable', 'string', 'max:255'],
            'users.*.email' => ['nullable', 'string', 'max:255'],
            'users.*.permission' => ['nullable', 'string', 'max:255'],
            'users.*.laborID' => ['nullable', 'integer'],
            'users.*.password' => ['nullable', 'string', 'max:255'],
            'users.*.receive_info' => ['nullable', 'boolean'],
            'users.*.signature' => ['nullable', 'string', 'max:255'],
        ]);

        $rows = $validated['users'];
        $errors = [];

        $payloadUserIds = [];
        foreach ($rows as $row) {
            if (! empty($row['userID'])) {
                $payloadUserIds[] = (int) $row['userID'];
            }
        }

        $existingById = $payloadUserIds === []
            ? collect()
            : User::query()
                ->whereIn('userID', $payloadUserIds)
                ->get(['userID', 'name'])
                ->keyBy('userID');

        $nameIndexes = [];
        foreach ($rows as $index => $row) {
            $name = trim((string) $row['name']);
            $email = trim((string) ($row['email'] ?? ''));
            $userId = $row['userID'] ?? null;
            $password = $row['password'] ?? null;

            if ($name === '') {
                $errors["users.{$index}.name"] = ['名前は必須です。'];
            } else {
                $nameIndexes[$name][] = $index;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors["users.{$index}.email"] = ['メールアドレスの形式が正しくありません。'];
            }

            if (! $userId && ($password === null || trim((string) $password) === '')) {
                $errors["users.{$index}.password"] = ['新規ユーザーにはパスワードが必要です。'];
            }
        }

        foreach ($nameIndexes as $name => $indexes) {
            if (count($indexes) > 1) {
                $allUnchangedLegacyDuplicates = true;
                foreach ($indexes as $index) {
                    $row = $rows[$index];
                    $userId = $row['userID'] ?? null;
                    if (! $userId) {
                        $allUnchangedLegacyDuplicates = false;
                        break;
                    }
                    $existingName = trim((string) ($existingById->get((int) $userId)?->name ?? ''));
                    if ($existingName !== $name) {
                        $allUnchangedLegacyDuplicates = false;
                        break;
                    }
                }

                if (! $allUnchangedLegacyDuplicates) {
                    foreach ($indexes as $index) {
                        $errors["users.{$index}.name"] = ['この名前は既に使用されています。'];
                    }
                }
                continue;
            }

            $index = $indexes[0];
            $userId = $rows[$index]['userID'] ?? null;
            $conflictQuery = User::query()->where('name', $name);
            if ($payloadUserIds !== []) {
                $conflictQuery->whereNotIn('userID', $payloadUserIds);
            } elseif ($userId) {
                $conflictQuery->where('userID', '!=', (int) $userId);
            }
            if ($conflictQuery->exists()) {
                $errors["users.{$index}.name"] = ['この名前は既に使用されています。'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                $userId = $row['userID'] ?? null;
                $payload = [
                    'name' => trim((string) $row['name']),
                    'kanji_name' => trim((string) ($row['kanji_name'] ?? '')),
                    'email' => trim((string) ($row['email'] ?? '')),
                    'permission' => ($row['permission'] ?? null) === '' || ($row['permission'] ?? null) === null
                        ? null
                        : trim((string) $row['permission']),
                    'laborID' => array_key_exists('laborID', $row) && $row['laborID'] !== null && $row['laborID'] !== ''
                        ? (int) $row['laborID']
                        : -1,
                    'receive_info' => ! empty($row['receive_info']) ? 1 : 0,
                    'signature' => trim((string) ($row['signature'] ?? '')),
                ];

                $password = isset($row['password']) ? trim((string) $row['password']) : '';

                if ($userId) {
                    $user = User::query()->where('userID', $userId)->firstOrFail();
                    $user->fill($payload);
                    if ($password !== '') {
                        $user->password = Hash::make($password);
                    }
                    $user->save();
                    continue;
                }

                User::create([
                    ...$payload,
                    'password' => Hash::make($password),
                ]);
            }
        });

        return response()->json([
            'message' => '保存しました。',
            'users' => $this->usersPayload(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertAdminPermission();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('users', 'name')],
            'kanji_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:255'],
            'laborID' => ['nullable', 'integer'],
            'receive_info' => ['nullable', 'boolean'],
            'signature' => ['nullable', 'string', 'max:255'],
        ]);

        $email = trim((string) ($validated['email'] ?? ''));
        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => ['メールアドレスの形式が正しくありません。'],
            ]);
        }

        $user = User::create([
            'name' => trim($validated['name']),
            'kanji_name' => trim((string) ($validated['kanji_name'] ?? '')),
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'permission' => ($validated['permission'] ?? null) === '' || ($validated['permission'] ?? null) === null
                ? null
                : trim((string) $validated['permission']),
            'laborID' => array_key_exists('laborID', $validated) && $validated['laborID'] !== null
                ? (int) $validated['laborID']
                : -1,
            'receive_info' => ! empty($validated['receive_info']) ? 1 : 0,
            'signature' => trim((string) ($validated['signature'] ?? '')),
        ]);

        return response()->json(['message' => '登録しました', 'user' => $user]);
    }
}
