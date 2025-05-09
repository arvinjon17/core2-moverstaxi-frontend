// Get employee data without cross-database joins
try {
    // Step 1: Get basic employee data from core1 database
    $employeesQuery = "SELECT 
        employee_id, user_id, position, hire_date, status, notes,
        created_at, updated_at 
    FROM employees
    ORDER BY hire_date DESC";
    
    error_log("Executing employees query: $employeesQuery");
    $employees = getRows($employeesQuery, 'core1');
    $employeeCount = count($employees);
    error_log("Found $employeeCount employees");
    
    // Step 2: Enrich employee data with user information from core2 database
    if (!empty($employees)) {
        foreach ($employees as &$employee) {
            // Get user data if available
            if (!empty($employee['user_id'])) {
                $userId = (int)$employee['user_id'];
                $userQuery = "SELECT 
                    name, email, phone 
                    FROM users 
                    WHERE user_id = $userId";
                $userData = getRows($userQuery, 'core2');
                
                if (!empty($userData[0])) {
                    $employee['name'] = $userData[0]['name'];
                    $employee['email'] = $userData[0]['email'];
                    $employee['phone'] = $userData[0]['phone'];
                } else {
                    $employee['name'] = 'Unknown';
                    $employee['email'] = '';
                    $employee['phone'] = '';
                }
            } else {
                $employee['name'] = 'Unknown';
                $employee['email'] = '';
                $employee['phone'] = '';
            }
            
            // Get booking count for this employee
            $employeeId = (int)$employee['employee_id'];
            $bookingCountQuery = "SELECT 
                COUNT(*) as booking_count
                FROM bookings 
                WHERE FIND_IN_SET('$employeeId', assigned_employees) > 0";
            $bookingCountData = getRows($bookingCountQuery, 'core2');
            
            if (!empty($bookingCountData[0])) {
                $employee['booking_count'] = $bookingCountData[0]['booking_count'];
            } else {
                $employee['booking_count'] = 0;
            }
        }
    }
    
    if (empty($employees)) {
        error_log("No employees found");
    } else {
        // Log the first employee for debugging
        error_log("First employee with user data: " . json_encode($employees[0]));
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Employees query failed in employees.php: " . $error);
    echo '<div class="alert alert-danger">Error fetching employees: ' . $error . '</div>';
} 