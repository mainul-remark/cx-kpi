<?php

namespace App\Imports\User;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    use Importable;

    private array $failures = [];
    private int $importedCount = 0;
    private array $roleMap = [];
    private array $existingEmails = [];
    private array $existingEmployeeIds = [];
    private array $seenEmails = [];
    private array $seenEmployeeIds = [];

    public function __construct()
    {
        Role::query()
            ->select('role_id', 'name')
            ->get()
            ->each(function (Role $role) {
                $this->roleMap[$this->normalize($role->name)] = (int) $role->role_id;
            });

        $this->existingEmails = User::query()
            ->pluck('email')
            ->filter()
            ->mapWithKeys(fn ($email) => [$this->normalize($email) => true])
            ->toArray();

        $this->existingEmployeeIds = User::query()
            ->pluck('employee_id')
            ->filter()
            ->mapWithKeys(fn ($employeeId) => [$this->normalize($employeeId) => true])
            ->toArray();
    }

    public function collection(Collection $rows): void
    {
        $excelRow = 1;
        $validRows = [];

        foreach ($rows as $row) {
            $excelRow++;

            $rowData = $row->toArray();
            $rowErrors = $this->validateRow($rowData);

            if ($rowErrors !== []) {
                $this->failures[] = [
                    'row'    => $excelRow,
                    'errors' => $rowErrors,
                ];
                continue;
            }

            $validRows[] = [
                'name'          => trim((string) $rowData['user_name']),
                'email'         => trim((string) $rowData['email']),
                'employee_id'   => trim((string) $rowData['employee_id']),
                'usages_sector' => strtolower(trim((string) $rowData['usages_sector'])),
                'role_id'       => $this->roleMap[$this->normalize($rowData['role'])],
            ];

            $this->seenEmails[$this->normalize($rowData['email'])] = true;
            $this->seenEmployeeIds[$this->normalize($rowData['employee_id'])] = true;
        }

        if ($validRows === []) {
            return;
        }

        DB::transaction(function () use ($validRows) {
            foreach ($validRows as $validRow) {
                $user = new User();
                $user->name = $validRow['name'];
                $user->email = $validRow['email'];
                $user->employee_id = $validRow['employee_id'];
                $user->usages_sector = $validRow['usages_sector'];
                $user->password = Hash::make('remarkhb');
                $user->save();

                UserRole::create([
                    'user_id' => $user->id,
                    'role_id' => $validRow['role_id'],
                ]);
            }
        });

        $this->importedCount = count($validRows);
    }

    private function validateRow(array $row): array
    {
        $errors = [];

        $userName = trim((string) ($row['user_name'] ?? ''));
        $email = trim((string) ($row['email'] ?? ''));
        $employeeId = trim((string) ($row['employee_id'] ?? ''));
        $usagesSector = strtolower(trim((string) ($row['usages_sector'] ?? '')));
        $role = trim((string) ($row['role'] ?? ''));

        if ($userName === '') {
            $errors[] = 'User Name is required.';
        }

        if ($email === '') {
            $errors[] = 'Email is required.';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email must be a valid email address.';
        }

        if ($employeeId === '') {
            $errors[] = 'Employee ID is required.';
        }

        if (! in_array($usagesSector, ['field', 'corporate'], true)) {
            $errors[] = 'Usages Sector must be field or corporate.';
        }

        if ($role === '') {
            $errors[] = 'Role is required.';
        } elseif (! isset($this->roleMap[$this->normalize($role)])) {
            $errors[] = "Role \"{$role}\" does not exist.";
        }

        $emailKey = $this->normalize($email);
        if ($emailKey !== '') {
            if (isset($this->existingEmails[$emailKey])) {
                $errors[] = "Email \"{$email}\" already exists.";
            } elseif (isset($this->seenEmails[$emailKey])) {
                $errors[] = "Email \"{$email}\" is duplicated in the file.";
            }
        }

        $employeeIdKey = $this->normalize($employeeId);
        if ($employeeIdKey !== '') {
            if (isset($this->existingEmployeeIds[$employeeIdKey])) {
                $errors[] = "Employee ID \"{$employeeId}\" already exists.";
            } elseif (isset($this->seenEmployeeIds[$employeeIdKey])) {
                $errors[] = "Employee ID \"{$employeeId}\" is duplicated in the file.";
            }
        }

        return $errors;
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
