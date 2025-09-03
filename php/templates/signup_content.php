<?php
// Signup content template for PHP implementation
?>
<div class="card">
    <h1 class="text-2xl font-bold mb-6">Sign Up</h1>
    
    <form action="/signup" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <div class="mb-4">
            <label for="display_name" class="block text-sm font-medium mb-2">Display Name</label>
            <input type="text" id="display_name" name="display_name" class="form-input" 
                   placeholder="Your display name" maxlength="50" required>
            <p class="text-muted text-sm">How others will see you (max 50 characters)</p>
        </div>
        
        <div class="mb-4">
            <label for="password" class="block text-sm font-medium mb-2">Password</label>
            <input type="password" id="password" name="password" class="form-input" 
                   placeholder="Choose a strong password" minlength="8" maxlength="128" required>
            <p class="text-muted text-sm">At least 8 characters</p>
        </div>
        
        <div class="mb-6">
            <label for="confirm_password" class="block text-sm font-medium mb-2">Confirm Password</label>
            <input type="password" id="confirm_password" name="confirm_password" class="form-input" 
                   placeholder="Confirm your password" required>
        </div>
        
        <button type="submit" class="btn w-full">Create Account</button>
    </form>
    
    <div class="mt-6 p-4 bg-opacity-20" style="background-color: var(--color-warning);">
        <h3 class="font-bold mb-2">⚠️ Important Security Information</h3>
        <ul class="text-sm space-y-1">
            <li>• You will receive a random 12-digit Account ID</li>
            <li>• A recovery phrase will be generated for account recovery</li>
            <li>• <strong>Save both securely</strong> - they cannot be recovered if lost</li>
            <li>• Your account is completely anonymous</li>
        </ul>
    </div>
    
    <div class="mt-4 text-center">
        <p class="text-muted">Already have an account? <a href="/login" class="text-primary hover:underline">Login here</a></p>
    </div>
</div>