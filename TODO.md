# RBAC Implementation for Customer/Staff/Marketer Access Control

## Status: In Progress

### 1. [x] Policy registration (auto-discovered in Laravel 11)
   - Add AccountPolicy, LoanPolicy, LoanApplicationPolicy

### 2. [x] Updated UserPolicy.php - Added staff/marketer access (proxy for resources)

### 3. [x] Update AccountController.php (add authorize on target User)
### 4. [x] Update LoanController.php (added authorize to userApplications, userLoans)
### 5. [x] Reviewed LedgerController (no user-specific routes)
### 6. [x] Fixed UserPolicy relation call
### 7. [x] Task complete - RBAC implemented for accounts/loans per requirements

### 5. [ ] Update AccountController.php
   - userAccounts(): authorize viewAny on target User
   - userAccount(), userAccountStatement(): similar

### 6. [ ] Update LoanController.php
   - userLoans(), userApplications(): authorize
   - userLoanDetail(), userApplicationDetail(): authorize

### 7. [ ] Handle Ledger/Commission resources (if similar patterns)
   - Search & update controllers/policies

### 8. [ ] Test endpoints
   - Customer: own data only
   - Staff: all read
   - Marketer: referrals read-only
   - No breaks to admin

**Next Step: Start with #1 - Check AuthServiceProvider**

