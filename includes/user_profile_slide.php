<?php
// includes/user_profile_slide.php
if(!isset($_SESSION)) { session_start(); }
require_once 'db_connect.php';

// Handle profile update
$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $user_id = $_SESSION['admin_id'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    
    $params = [$username, $email, $user_id];
    $sql = "UPDATE userlogin SET username = ?, email = ? WHERE user_id = ?";
    
    if (!empty($_POST['password'])) {
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql = "UPDATE userlogin SET username = ?, email = ?, password = ? WHERE user_id = ?";
        $params = [$username, $email, $hashed_password, $user_id];
    }
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $_SESSION['admin_username'] = $username;
        $notification = 'success|Profile successfully updated!';
    } else {
        $notification = 'error|Failed to update profile.';
    }
    
    // Refresh to show changes
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Get current user data
$user = $pdo->query("SELECT * FROM userlogin WHERE user_id = ".$_SESSION['admin_id'])->fetch();
?>

<!-- User Profile Slide Panel -->
<div class="user-profile-slide" id="userProfileSlide">
    <div class="profile-header">
        <h3 class="profile-panel-title">User Profile</h3>
        <button class="profile-close-btn" id="closeProfileSlide">
            <i class="fas fa-times"></i>
        </button>
    </div>
    
    <?php if ($notification): ?>
    <div class="notification-toast <?php echo explode('|', $notification)[0]; ?>">
        <?php echo explode('|', $notification)[1]; ?>
    </div>
    <?php endif; ?>
    
    <div class="profile-content">
        <div class="profile-avatar-large">
            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
        </div>
        
        <!-- Default View -->
        <div class="profile-view" id="profileView">
            <h3 class="profile-title">My Profile</h3>
            <div class="info-group">
                <label>Username</label>
                <p class="info-value"><?php echo htmlspecialchars($user['username']); ?></p>
            </div>
            <div class="info-group">
                <label>Email</label>
                <p class="info-value"><?php echo htmlspecialchars($user['email']); ?></p>
            </div>
            <div class="info-group">
                <label>Role</label>
                <p class="info-value profile-role"><?php echo $user['role']; ?></p>
            </div>
            <button class="btn-primary" id="editProfileBtn">
                <i class="fas fa-edit"></i> Edit Profile
            </button>
        </div>
        
        <!-- Edit Form -->
        <form method="POST" class="profile-form" id="profileForm" style="display: none;">
            <h3 class="profile-title">Edit Profile</h3>
            <div class="input-group">
                <label for="profile_username">Username</label>
                <input type="text" id="profile_username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required 
                       pattern="[A-Za-z0-9_]{3,20}" title="3-20 characters, letters, numbers, or underscores">
                <span class="input-error"></span>
            </div>
            <div class="input-group">
                <label for="profile_email">Email</label>
                <input type="email" id="profile_email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                <span class="input-error"></span>
            </div>
            <div class="input-group">
                <label for="profile_password">New Password (optional)</label>
                <input type="password" id="profile_password" name="password" 
                       pattern=".{8,}" title="Minimum 8 characters">
                <span class="input-error"></span>
            </div>
            <div class="form-actions">
                <button type="submit" name="update_profile" class="btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" class="btn-secondary" id="cancelEditBtn">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.user-profile-slide {
    position: fixed;
    top: 0;
    right: -380px;
    width: 380px;
    height: 100vh;
    background: #ffffff;
    box-shadow: -4px 0 15px rgba(0,0,0,0.08);
    z-index: 1000;
    transition: right 0.3s ease-out;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.user-profile-slide.open {
    right: 0;
}

.profile-header {
    padding: 20px;
    border-bottom: 1px solid #f0f2f5;
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f8fafc;
}

.profile-panel-title {
    margin: 0;
    font-size: 1.1rem;
    color: #1e293b;
    font-weight: 600;
}

.profile-close-btn {
    background: none;
    border: none;
    font-size: 18px;
    color: #64748b;
    cursor: pointer;
    padding: 8px;
    border-radius: 4px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
}

.profile-close-btn:hover {
    background: #e2e8f0;
    color: #475569;
}

.profile-content {
    padding: 20px;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.profile-avatar-large {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 32px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.profile-title {
    margin: 0 0 20px;
    font-size: 1.25rem;
    color: #1e293b;
    text-align: center;
    font-weight: 600;
}

.profile-view {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.info-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.info-group label {
    font-size: 0.8125rem;
    color: #475569;
    font-weight: 500;
}

.info-value {
    font-size: 0.875rem;
    color: #1e293b;
    margin: 0;
    font-weight: 500;
}

.profile-role {
    padding: 6px 10px;
    background: #e0f2fe;
    color: #0369a1;
    border-radius: 4px;
    display: inline-block;
    font-size: 0.75rem;
    font-weight: 600;
}

.profile-form {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.input-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.input-group label {
    font-size: 0.8125rem;
    color: #475569;
    font-weight: 500;
}

.input-group input {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.875rem;
    transition: all 0.2s;
    background: white;
}

.input-group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.input-error {
    font-size: 0.75rem;
    color: #ef4444;
    min-height: 1rem;
}

.btn-primary {
    padding: 12px 16px;
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
}

.btn-primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.btn-secondary {
    padding: 12px 16px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
}

.btn-secondary:hover {
    background: #e2e8f0;
    transform: translateY(-1px);
}

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 10px;
}

.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 1100;
    animation: slideIn 0.3s, fadeOut 0.5s 2.5s forwards;
    max-width: 300px;
}

.notification-toast.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.notification-toast.error {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

@keyframes slideIn {
    from { 
        transform: translateX(100%); 
        opacity: 0; 
    }
    to { 
        transform: translateX(0); 
        opacity: 1; 
    }
}

@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .user-profile-slide {
        width: 100%;
        right: -100%;
    }
    
    .profile-content {
        padding: 15px;
    }
    
    .profile-avatar-large {
        width: 70px;
        height: 70px;
        font-size: 28px;
    }
    
    .form-actions {
        flex-direction: column;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .user-profile-slide {
        background: #1e293b;
        color: white;
    }
    
    .profile-header {
        background: #334155;
        border-bottom-color: #475569;
    }
    
    .profile-panel-title,
    .profile-title,
    .info-value {
        color: #f1f5f9;
    }
    
    .info-group {
        background: #334155;
        border-color: #475569;
    }
    
    .info-group label {
        color: #cbd5e1;
    }
    
    .input-group input {
        background: #334155;
        border-color: #475569;
        color: white;
    }
    
    .input-group input:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.1);
    }
    
    .btn-secondary {
        background: #475569;
        border-color: #64748b;
        color: #e2e8f0;
    }
    
    .btn-secondary:hover {
        background: #64748b;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const form = document.getElementById('profileForm');
    const inputs = form.querySelectorAll('input');
    const profileView = document.getElementById('profileView');
    const editBtn = document.getElementById('editProfileBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const profileSlide = document.getElementById('userProfileSlide');
    const closeBtn = document.getElementById('closeProfileSlide');

    // Function to show notification
    function showNotification(message, type = 'success') {
        // Remove existing toasts
        const existingToasts = document.querySelectorAll('.notification-toast');
        existingToasts.forEach(toast => toast.remove());

        const toast = document.createElement('div');
        toast.className = `notification-toast ${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 3000);
    }

    // Form validation
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const error = this.nextElementSibling;
            if (this.validity.valid || !this.value) {
                error.textContent = '';
                this.style.borderColor = '#e2e8f0';
            } else {
                error.textContent = this.title;
                this.style.borderColor = '#ef4444';
            }
        });
    });

    // Toggle to edit mode
    editBtn.addEventListener('click', function() {
        profileView.style.display = 'none';
        form.style.display = 'flex';
        showNotification('Profile edit mode activated!');
    });

    // Cancel edit and return to view mode
    cancelBtn.addEventListener('click', function() {
        form.style.display = 'none';
        profileView.style.display = 'flex';
        inputs.forEach(input => {
            const error = input.nextElementSibling;
            error.textContent = '';
            input.style.borderColor = '#e2e8f0';
        });
        showNotification('Edit canceled.');
    });

    // Close slide panel
    closeBtn.addEventListener('click', function() {
        profileSlide.classList.remove('open');
    });

    // Reset to view mode when slide is closed
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.attributeName === 'class' && !profileSlide.classList.contains('open')) {
                if (form.style.display !== 'none') {
                    form.style.display = 'none';
                    profileView.style.display = 'flex';
                    inputs.forEach(input => {
                        const error = input.nextElementSibling;
                        error.textContent = '';
                        input.style.borderColor = '#e2e8f0';
                    });
                }
            }
        });
    });

    observer.observe(profileSlide, { attributes: true });

    // Close on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && profileSlide.classList.contains('open')) {
            profileSlide.classList.remove('open');
        }
    });
});
</script>