# OpenDoor API Roles and Access Control

This document outlines the role-based access control (RBAC) system implemented in the OpenDoor API platform.

## Roles Overview

The system uses Spatie Laravel Permission package for role management. The following roles are defined:

### 1. Customer
- **Description**: End users who can access their own accounts, apply for loans, and manage their financial data
- **Assigned automatically** during user registration
- **Permissions**:
  - View own profile (`/api/v1/me`)
  - Manage own accounts (view, debit, credit)
  - Apply for loans
  - Make loan repayments
  - Upload KYC documents
  - View KYC status
  - View loan products

### 2. Admin
- **Description**: System administrators with full access to all resources
- **Assigned manually** by existing admins
- **Permissions**:
  - All customer permissions
  - Create/manage account types
  - Approve/reject loan applications
  - Disburse approved loans
  - View trial balance
  - Close daily books
  - View audit logs
  - Manage fraud flags
  - All auditor permissions

### 3. Auditor
- **Description**: Financial auditors who can monitor transactions and financial reports
- **Assigned manually** by admins
- **Permissions**:
  - View trial balance
  - View audit logs
  - View fraud flags
  - Cannot modify any data

### 4. Staff
- **Description**: Support staff with limited operational access
- **Assigned manually** by admins
- **Permissions**:
  - View customer profiles (limited)
  - View account information (limited)
  - View loan applications
  - Cannot approve loans or modify financial data

## Route Access Matrix

| Endpoint | Method | Customer | Admin | Auditor | Staff |
|----------|--------|----------|-------|---------|-------|
| `/register` | POST | ✅ Public | ✅ Public | ✅ Public | ✅ Public |
| `/login` | POST | ✅ Public | ✅ Public | ✅ Public | ✅ Public |
| `/logout` | POST | ✅ | ✅ | ✅ | ✅ |
| `/refresh` | POST | ✅ | ✅ | ✅ | ✅ |
| `/me` | GET | ✅ | ✅ | ✅ | ✅ |
| `/kyc/upload` | POST | ✅ | ❌ | ❌ | ❌ |
| `/kyc/status` | GET | ✅ | ❌ | ❌ | ❌ |
| `/account-types` | GET | ✅ | ✅ | ❌ | ❌ |
| `/account-types` | POST | ❌ | ✅ | ❌ | ❌ |
| `/accounts` | GET/POST | ✅ | ✅ | ❌ | ❌ |
| `/accounts/{id}/debit` | POST | ✅ | ✅ | ❌ | ❌ |
| `/accounts/{id}/credit` | POST | ✅ | ✅ | ❌ | ❌ |
| `/loan-products` | GET | ✅ | ✅ | ❌ | ❌ |
| `/loans` | GET/POST | ✅ | ✅ | ❌ | ❌ |
| `/loans/{id}/approve` | POST | ❌ | ✅ | ❌ | ❌ |
| `/loans/{id}/disburse` | POST | ❌ | ✅ | ❌ | ❌ |
| `/loans/{id}/repay` | POST | ✅ | ✅ | ❌ | ❌ |
| `/ledger/trial-balance` | GET | ❌ | ✅ | ✅ | ❌ |
| `/ledger/close-day` | POST | ❌ | ✅ | ❌ | ❌ |
| `/audit-logs` | GET | ❌ | ✅ | ✅ | ❌ |
| `/fraud-flags` | GET | ❌ | ✅ | ✅ | ❌ |

## Middleware Implementation

Routes are protected using Laravel middleware groups:

```php
// routes/api.php
Route::middleware(['auth:api'])->group(function () {
    // Authenticated routes
});

Route::middleware(['auth:api', 'role:admin'])->group(function () {
    // Admin-only routes
});

Route::middleware(['auth:api', 'role:auditor'])->group(function () {
    // Auditor-only routes
});

Route::middleware(['auth:api', 'role:customer'])->group(function () {
    // Customer-only routes
});
```

## Security Considerations

1. **Role Assignment**: Only admins can assign roles to users
2. **Principle of Least Privilege**: Users get minimum permissions required for their role
3. **Audit Trail**: All role changes are logged in audit logs
4. **JWT Tokens**: Include role information for client-side role checking
5. **API Rate Limiting**: Different limits based on user roles

## Adding New Roles

To add a new role:

1. Create the role in the database:
```php
Role::create(['name' => 'new_role']);
```

2. Assign permissions to the role:
```php
$role = Role::findByName('new_role');
$role->givePermissionTo(['permission1', 'permission2']);
```

3. Update this documentation
4. Update route middleware if needed
5. Add role-specific tests

## Testing Role Access

Role-based access is tested in feature tests:

- `AuthTest`: Tests authentication flows
- `LoanTest`: Tests loan operations with different roles
- `LedgerTest`: Tests financial operations access control

Run tests with:
```bash
php artisan test --testsuite=Feature
```