<?php

class ValidationHelper {
    /**
     * Sanitize input to prevent SQL injection
     * @param string|null $input The input to sanitize
     * @return string|null The sanitized input
     */
    private static function sanitizeInput(?string $input): ?string {
        if ($input === null) {
            return null;
        }
        $input = trim($input);        
        return $input;
    }

    /**
     * Basic validation for empty fields and minimum length
     * @throws Exception If validation fails
     */
    private static function isValid(?string $str, string $fieldName): void {
        if ($str === null || $str === '') {
            throw new Exception("$fieldName cannot be empty");
        }

        if (strlen($str) < 5) {
            throw new Exception("$fieldName must be at least 5 characters");
        }
    }

    public static function validateUsername(?string $username): ?string {
        try {
            $username = self::sanitizeInput($username);
            self::isValid($username, 'username');
            
            if (strlen($username) > 20) {
                return "Username must be less than 20 characters";
            }
            
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
                return "Username can only contain letters, numbers, and underscores";
            }
            
            if (preg_match('/(union|select|insert|delete|update|drop|alter|create|exec|--|\/\*|\*\/)/i', $username)) {
                return "Username contains invalid characters";
            }
            
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function validatePassword(?string $password): ?string {
        try {
            $password = self::sanitizeInput($password);
            self::isValid($password, 'password');
            
            if (strlen($password) > 64) {
                return "Password must be less than 64 characters";
            }
            
            if (!preg_match('/[A-Z]/', $password)) {
                return "Password must contain at least one uppercase letter";
            }
            
            if (!preg_match('/[a-z]/', $password)) {
                return "Password must contain at least one lowercase letter";
            }
            
            if (!preg_match('/[0-9]/', $password)) {
                return "Password must contain at least one number";
            }
            
            if (!preg_match('/[\W]/', $password)) {
                return "Password must contain at least one special character";
            }
            
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function validateEmail(?string $email): ?string {
        try {
            $email = self::sanitizeInput($email);
            self::isValid($email, 'email');
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return "Invalid email format";
            }
            
            $domain = substr(strrchr($email, "@"), 1);
            if (!checkdnsrr($domain, "MX")) {
                return "Email domain does not exist";
            }
            
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public static function validatePhoneNumber(?string $phoneNumber): ?string {
        try {
            $phoneNumber = self::sanitizeInput($phoneNumber);
            self::isValid($phoneNumber, 'phone number');
            
            $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            if (strlen($cleaned) < 10) {
                return "Phone number must be at least 10 digits";
            }
            
            if (strlen($cleaned) > 15) {
                return "Phone number must be less than 15 digits";
            }
            
            return null;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}