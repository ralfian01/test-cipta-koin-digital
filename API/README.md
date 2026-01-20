# laravel-multi-user
This repository is an example implementation of a multi-user system using Laravel. The system allows users to log in with multiple roles that have different access permissions.

## Features
- Multi-user login system with role-based authentication.
- Role and access management through the command line using `php artisan`.

## Prerequisites
1. PHP __8.2+__
2. Laravel __11+__
3. Composer
4. MariaDB, MySQL, or PostgreSQL


<br><br>
## Installation
1. Clone this repository
2. Install all dependencies using Composer
   ```bash
   composer update
   ```
3. Rename file __*.env.example*__ to __*.env*__
4. Generate Laravel application key
   ```bash
   php artisan key:generate
   ```
5. Configure the __*.env*__ file according to your database settings
6. Run the migrations to create the necessary tables
   ```bash
   php artisan migrate
   ```
7. Run the seeder sequentially to insert the necessary rows
   ```bash
   php artisan db:seed --class=PrivilegeSeed
   php artisan db:seed --class=RoleSeed
   php artisan db:seed --class=RootAdminSeed
   php artisan db:seed --class=UserSeed
   ```
7. Start the development server
   ```bash
   php artisan serve
   ```

<br><br>
## Usage
### Privileges
#### Show list of privileges
To show list of privileges, use the following command:
```bash
php artisan privilege:view
```
artisan will show list of privileges:
```
+----+--------------------------+-----------------------------+
| id | code                     | description                 |
+----+--------------------------+-----------------------------+
| 1  | ACCOUNT_MANAGE_VIEW      | Show account list           |
| 2  | ACCOUNT_MANAGE_SUSPEND   | Suspend or activate account |
...
```

#### Adding new privilege
To add a new privilege, use the following command:
```bash
php artisan privilege:insert {code} {description}
```
example:
```bash
php artisan privilege:insert "ACCOUNT_SUSPEND" "Suspend account"
```

#### Update privilege
To update privilege, use the following command:
```bash
php artisan privilege:update {id_or_code} --code --description
```
example:
```bash
php artisan privilege:update ACCOUNT_SUSPEND --code="ACCOUNT_SUSPEND" --description="Suspend account"
```

#### Delete privilege
To delete privilege, use the following command:
```bash
php artisan privilege:delete {id_or_code}
```
example:
```bash
php artisan privilege:delete
```

<br><br>
### Roles
#### Show list of roles
To show list of roles, use the following command:
```bash
php artisan role:view
```
artisan will show list of role:
```
+----+--------------+--------------------+---------------------------+
| id | code         | name               | privileges                |
+----+--------------+--------------------+---------------------------+
| 1  | MASTER_ADMIN | Master Admin       | ACCOUNT_MANAGE_VIEW,      |
|    |              |                    | ADMIN_MANAGE_ADD,         |
|    |              |                    | ADMIN_MANAGE_PRIVILEGE,   |
|    |              |                    | ADMIN_MANAGE_SUSPEND,     |
|    |              |                    | ADMIN_MANAGE_VIEW,        |
...
```

#### Adding new role
To add a new role, use the following command:
```bash
php artisan role:insert {code} {name} --privileges
```
example:
```bash
php artisan role:insert "MEMBER" "Role Member" --privileges="ACCOUNT_MANAGE_VIEW,ACCOUNT_MANAGE_SUSPEND"
```

#### Update role
To update role, use the following command:
```bash
php artisan role:update {id_or_code} --code --name --add-privileges --delete-privileges
```
example:
```bash
php artisan role:update ACCOUNT_SUSPEND --code="ACCOUNT_SUSPEND" --name="Suspend account" --add-privileges="ACCOUNT_MANAGE_PRIVILEGE"
```

#### Delete role
To delete role, use the following command:
```bash
php artisan role:delete {id_or_code}
```
example:
```bash
php artisan role:delete
```

<br><br>
## Multi-user Usage
Test multi user systems with API
### Before we start
1. Make sure you have installed Postman on your computer
2. Import collection file in **Postman/Laravel-Multi-User.postman_collection.json** to your Postman Collection
3. Open "Laravel-Multi-User" collection and then go to "Variables" tab
4. Fill in the "Current Value" column with the hostname of your currently running Laravel development server.<br> Example: `http://localhost:8000/api`
5. Run Laravel development server

### Test Multi-user as Root Admin
1. Open postman request named "Login as Root Admin" in "Laravel-Multi-User" collection
2. Click "Send" button to request auth token to our Laravel API server
3. If authorization is successful, the API server will respond with:
```json
{
   "code": 200,
   "status": "SUCCESS",
   "message": "Success",
   "data": {
      "token": {jwt token}
   }
}
```
4. The variable "BEARER_TOKEN" will be filled in automatically
5. Test **/endpoint1** and **/endpoint2** by open postman request named "Test Endpoint 1" and "Test Endpoint 2"
6. The API server will respond with the json below for requests **/endpoint1** and **/endpoint2**: 
```json
{
   "code": 200,
   "status": "SUCCESS",
   "message": "Success",
   "data": null
}
```

### Test Multi-user as User
1. Open postman request named "Login as User" in "Laravel-Multi-User" collection
2. Click "Send" button to request auth token to our Laravel API server
3. If authorization is successful, the API server will respond with:
```json
{
   "code": 200,
   "status": "SUCCESS",
   "message": "Success",
   "data": {
      "token": {jwt token}
   }
}
```
4. The variable "BEARER_TOKEN" will be filled in automatically
5. Test **/endpoint1** and **/endpoint2** by open postman request named "Test Endpoint 1" and "Test Endpoint 2"
6. For **/endpoint1** requests, the API server will respond with the json below:
```json
{
   "code": 200,
   "status": "SUCCESS",
   "message": "Success",
   "data": null
}
```
7. For **/endpoint2** requests, the API server will respond with the json below:
```json
{
   "code": 401,
   "status": "UNAUTHORIZED",
   "message": "You do not have permission to access this resource",
   "error_detail": []
}
```

### Why did that happen?
That's because in the class that handles requests to **/endpoint2** there is a _**$privilegeRules**_ property filled with "ADMIN_MANAGE_VIEW" which the User account doesn't have, while the Root Admin account does. So Users cannot access the endpoint, but Root Admin can access it.
<br><br>
For further details, you can learn by looking at the contents of the routes and classes in **/routes/api.php**
