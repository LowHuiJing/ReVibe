<?php
require_once 'includes/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

checkSessionTimeout();

if (!isset($_SESSION['user_id'])) {
    $page_title = 'Account Settings — REVIBE';
    require_once 'includes/header.php';
    echo '<main style="max-width:500px;margin:80px auto;padding:0 1.5rem;text-align:center;">
        <div style="background:#fff;border-radius:20px;box-shadow:0 2px 14px rgba(0,0,0,.08);padding:2.5rem 2rem;">
            <div style="font-size:2.5rem;margin-bottom:1rem;">🔒</div>
            <h1 style="font-size:1.2rem;font-weight:900;margin:0 0 .5rem;">Sign in to access account settings</h1>
            <p style="color:#888;font-size:.88rem;margin:0 0 1.5rem;">Manage your profile, password, and preferences.</p>
            <div style="display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;">
                <a href="/revibe/signin.php" style="padding:.7rem 1.6rem;background:#1a1a1a;color:#c8f04a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">Sign In</a>
                <a href="/revibe/signup.php" style="padding:.7rem 1.6rem;background:#fff;color:#1a1a1a;border:2px solid #1a1a1a;border-radius:10px;font-weight:700;font-size:.88rem;text-decoration:none;">Create Account</a>
            </div>
        </div>
    </main>';
    require_once 'includes/footer.php';
    exit;
}

$user_id = $_SESSION['user_id'];
$errors  = [];
$success = '';

function fetchUser($conn, $user_id) {
    $r = mysqli_query($conn,
        "SELECT u.username, u.email,
                p.first_name, p.last_name, p.bio, p.phone, p.avatar_url, p.city, p.country,
                s.email_notifications, s.sms_notifications,
                s.language, s.timezone, s.theme, s.privacy_level
         FROM users u
         LEFT JOIN user_profiles p ON u.id = p.user_id
         LEFT JOIN user_settings s ON u.id = s.user_id
         WHERE u.id = $user_id"
    );
    return mysqli_fetch_assoc($r);
}

$user = fetchUser($conn, $user_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update personal info
    if (isset($_POST['update_profile'])) {
        $first = mysqli_real_escape_string($conn, trim($_POST['first_name'] ?? ''));
        $last  = mysqli_real_escape_string($conn, trim($_POST['last_name']  ?? ''));
        $bio   = mysqli_real_escape_string($conn, trim($_POST['bio']        ?? ''));
        $phone = mysqli_real_escape_string($conn, trim($_POST['phone']      ?? ''));
        $city  = mysqli_real_escape_string($conn, trim($_POST['city']       ?? ''));
        $cntry = mysqli_real_escape_string($conn, trim($_POST['country']    ?? ''));

        $ok = mysqli_query($conn,
            "UPDATE user_profiles SET first_name='$first', last_name='$last', bio='$bio',
                                      phone='$phone', city='$city', country='$cntry'
             WHERE user_id=$user_id"
        );
        $success = $ok ? 'Profile updated successfully.' : 'Failed to update profile.';
    }

    // Change password
    if (isset($_POST['update_password'])) {
        $cur = $_POST['current_password'] ?? '';
        $new = $_POST['new_password']     ?? '';
        $cfm = $_POST['confirm_password'] ?? '';

        if (empty($cur) || empty($new) || empty($cfm)) {
            $errors[] = 'Please fill in all password fields.';
        } elseif (strlen($new) < 8) {
            $errors[] = 'New password must be at least 8 characters.';
        } elseif ($new !== $cfm) {
            $errors[] = 'New passwords do not match.';
        } else {
            $r = changePassword($user_id, $cur, $new);
            if ($r['success']) $success = $r['message'];
            else               $errors[] = $r['message'];
        }
    }

    // Update preferences
    if (isset($_POST['update_preferences'])) {
        $email_notif = isset($_POST['email_notifications']) ? 1 : 0;
        $sms_notif   = isset($_POST['sms_notifications'])   ? 1 : 0;
        $lang        = mysqli_real_escape_string($conn, $_POST['language']      ?? 'en');
        $tz          = mysqli_real_escape_string($conn, $_POST['timezone']      ?? 'Asia/Kuala_Lumpur');
        $theme       = mysqli_real_escape_string($conn, $_POST['theme']         ?? 'system');
        $privacy     = mysqli_real_escape_string($conn, $_POST['privacy_level'] ?? 'public');

        $ok = mysqli_query($conn,
            "UPDATE user_settings SET email_notifications=$email_notif, sms_notifications=$sms_notif,
                                      language='$lang', timezone='$tz', theme='$theme', privacy_level='$privacy'
             WHERE user_id=$user_id"
        );
        $success = $ok ? 'Preferences updated.' : 'Failed to update preferences.';
    }

    $user = fetchUser($conn, $user_id);
}

$page_title = 'Account Settings — REVIBE';
require_once 'includes/header.php';
?>

<style>
body.dark { background: #0f172a; }
body.dark .acc-heading     { color: #e2e8f0 !important; }
body.dark .acc-subheading  { color: #64748b !important; }
body.dark .acc-card        { background: #1e293b !important; box-shadow: 0 2px 12px rgba(0,0,0,.3) !important; }
body.dark .acc-card h2     { color: #e2e8f0 !important; }
body.dark .acc-label       { color: #94a3b8 !important; }
body.dark .acc-hint        { color: #475569 !important; }
body.dark .acc-input       { background: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
body.dark .acc-input:focus { border-color: #c8f04a !important; }
body.dark .acc-input:disabled { background: #1a2744 !important; color: #475569 !important; cursor: not-allowed; }
body.dark .acc-check-label { color: #cbd5e1 !important; }
</style>

<main style="max-width:760px;margin:40px auto;padding:0 1.5rem;">

    <div style="margin-bottom:1.8rem;">
        <h1 class="acc-heading" style="font-size:1.4rem;font-weight:900;margin:0 0 4px;">Account Settings</h1>
        <p class="acc-subheading" style="color:#888;font-size:.88rem;margin:0;">Manage your profile, security and preferences</p>
    </div>

    <?php if (!empty($errors)): ?>
    <div style="background:#fdecea;color:#c62828;padding:.9rem 1rem;border-radius:10px;margin-bottom:1.4rem;font-size:.88rem;">
        <?php foreach ($errors as $e): ?><p style="margin:0 0 2px;">• <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div style="background:#dcfce7;color:#166534;padding:.9rem 1rem;border-radius:10px;margin-bottom:1.4rem;font-size:.88rem;">
        ✓ <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php
    $card = 'background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:1.4rem;overflow:hidden;';
    $hdr  = 'padding:1.1rem 1.4rem;border-bottom:2px solid #c8f04a;';
    $h2   = 'font-size:1rem;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin:0;';
    $bod  = 'padding:1.4rem;';
    $fi   = 'width:100%;padding:.65rem .9rem;border:1.5px solid #ddd;border-radius:8px;font-size:.9rem;font-family:inherit;outline:none;box-sizing:border-box;';
    $fl   = 'display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#444;margin-bottom:.35rem;';
    $fg   = 'margin-bottom:1rem;';
    $sbtn = 'padding:.7rem 1.6rem;background:#1a1a1a;color:#c8f04a;border:none;border-radius:10px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;';
    ?>

    <!-- Personal Info -->
    <div class="acc-card" style="<?= $card ?>">
        <div style="<?= $hdr ?>"><h2 style="<?= $h2 ?>">Personal Information</h2></div>
        <div style="<?= $bod ?>">
            <form method="POST">
                <div style="<?= $fg ?>">
                    <label class="acc-label" style="<?= $fl ?>">Username</label>
                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled
                           class="acc-input" style="<?= $fi ?>background:#f9f9f9;cursor:not-allowed;">
                    <p class="acc-hint" style="font-size:.75rem;color:#aaa;margin:4px 0 0;">Username cannot be changed.</p>
                </div>
                <div style="<?= $fg ?>">
                    <label class="acc-label" style="<?= $fl ?>">Email Address</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled
                           class="acc-input" style="<?= $fi ?>background:#f9f9f9;cursor:not-allowed;">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div style="<?= $fg ?>">
                        <label class="acc-label" style="<?= $fl ?>">First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>"
                               placeholder="Your first name" class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                    </div>
                    <div style="<?= $fg ?>">
                        <label class="acc-label" style="<?= $fl ?>">Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>"
                               placeholder="Your last name" class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                    </div>
                </div>
                <div style="<?= $fg ?>">
                    <label class="acc-label" style="<?= $fl ?>">Bio</label>
                    <textarea name="bio" rows="3" placeholder="Tell others about yourself"
                              class="acc-input" style="<?= $fi ?>resize:vertical;" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                <div style="<?= $fg ?>">
                    <label class="acc-label" style="<?= $fl ?>">Phone</label>
                    <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                           placeholder="Your phone number" class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem;">
                    <div>
                        <label class="acc-label" style="<?= $fl ?>">City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($user['city'] ?? '') ?>"
                               placeholder="Your city" class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                    </div>
                    <div>
                        <label class="acc-label" style="<?= $fl ?>">Country</label>
                        <input type="text" name="country" value="<?= htmlspecialchars($user['country'] ?? '') ?>"
                               placeholder="Your country" class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                    </div>
                </div>
                <button type="submit" name="update_profile" style="<?= $sbtn ?>">Save Changes</button>
            </form>
        </div>
    </div>

    <!-- Change Password -->
    <div class="acc-card" style="<?= $card ?>">
        <div style="<?= $hdr ?>"><h2 style="<?= $h2 ?>">Change Password</h2></div>
        <div style="<?= $bod ?>">
            <form method="POST">
                <div style="<?= $fg ?>">
                    <label class="acc-label" style="<?= $fl ?>">Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter current password"
                           class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                </div>
                <div style="<?= $fg ?>">
                    <label class="acc-label" style="<?= $fl ?>">New Password</label>
                    <input type="password" name="new_password" placeholder="Minimum 8 characters"
                           class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                </div>
                <div style="margin-bottom:1.2rem;">
                    <label class="acc-label" style="<?= $fl ?>">Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Repeat new password"
                           class="acc-input" style="<?= $fi ?>" onfocus="this.style.borderColor='#c8f04a'" onblur="this.style.borderColor=''">
                </div>
                <button type="submit" name="update_password" style="<?= $sbtn ?>">Update Password</button>
            </form>
        </div>
    </div>

    <!-- Preferences -->
    <div class="acc-card" style="<?= $card ?>">
        <div style="<?= $hdr ?>"><h2 style="<?= $h2 ?>">Preferences</h2></div>
        <div style="<?= $bod ?>">
            <form method="POST">
                <div style="<?= $fg ?>">
                    <label class="acc-label" style="<?= $fl ?>">Notifications</label>
                    <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:.4rem;">
                        <label class="acc-check-label" style="display:flex;align-items:center;gap:.6rem;font-weight:400;font-size:.9rem;cursor:pointer;">
                            <input type="checkbox" name="email_notifications" value="1"
                                   <?= ($user['email_notifications'] ?? 0) ? 'checked' : '' ?> style="accent-color:#c8f04a;">
                            Email Notifications
                        </label>
                        <label class="acc-check-label" style="display:flex;align-items:center;gap:.6rem;font-weight:400;font-size:.9rem;cursor:pointer;">
                            <input type="checkbox" name="sms_notifications" value="1"
                                   <?= ($user['sms_notifications'] ?? 0) ? 'checked' : '' ?> style="accent-color:#c8f04a;">
                            SMS Notifications
                        </label>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;<?= $fg ?>">
                    <div>
                        <label class="acc-label" style="<?= $fl ?>">Language</label>
                        <select name="language" class="acc-input" style="<?= $fi ?>">
                            <?php foreach (['en'=>'English','ms'=>'Bahasa Melayu','zh'=>'Chinese','ar'=>'Arabic'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($user['language'] ?? 'en')===$v ? 'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="acc-label" style="<?= $fl ?>">Theme</label>
                        <select name="theme" class="acc-input" style="<?= $fi ?>">
                            <?php foreach (['system'=>'System Default','light'=>'Light','dark'=>'Dark'] as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= ($user['theme'] ?? 'system')===$v ? 'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:1.2rem;">
                    <label class="acc-label" style="<?= $fl ?>">Profile Privacy</label>
                    <select name="privacy_level" class="acc-input" style="<?= $fi ?>">
                        <option value="public"  <?= ($user['privacy_level'] ?? 'public')==='public'  ? 'selected':'' ?>>Public — Anyone can view</option>
                        <option value="private" <?= ($user['privacy_level'] ?? '')==='private' ? 'selected':'' ?>>Private — Only me</option>
                    </select>
                </div>
                <button type="submit" name="update_preferences" style="<?= $sbtn ?>">Save Preferences</button>
            </form>
        </div>
    </div>

</main>
<?php require_once 'includes/footer.php'; ?>
