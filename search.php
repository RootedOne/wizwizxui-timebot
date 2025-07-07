<?php
if(!file_exists("baseInfo.php") || !file_exists("config.php")){
    form("فایل های مورد نیاز یافت نشد");
    exit();
}

require "baseInfo.php";
require "config.php";
include "jdf.php";

// Ensure $buttonValues is available, provide defaults if not.
$buttonValues = $GLOBALS['buttonValues'] ?? ['active' => "فعال 🟢", 'deactive' => "غیر فعال 🔴"];

// Initialize display variables to prevent errors in HTML if a config is not found.
$GLOBALS['remark'] = 'یافت نشد';
$GLOBALS['total'] = 'N/A';
$GLOBALS['totalUsed'] = 'N/A';
$GLOBALS['state'] = 'N/A';
$GLOBALS['expiryTime'] = 'N/A';
$GLOBALS['leftMb'] = 'N/A';
$GLOBALS['expiryDay'] = 'N/A';
$GLOBALS['upload'] = 'N/A';
$GLOBALS['download'] = 'N/A';
$isMarzban = false; // Default to not Marzban for HTML conditional rendering

if(isset($_REQUEST['id'])){
    $input_id = $_REQUEST['id'];
    $config_link_input = $input_id;
    $uuid_or_username_to_search = $input_id;

    if(preg_match('/^vmess:\/\/(.*)/',$input_id,$match)){
        $decoded_vmess = json_decode(base64_decode($match[1]),true);
        if (isset($decoded_vmess['id'])) {
            $uuid_or_username_to_search = $decoded_vmess['id'];
        }
    }elseif(preg_match('/^vless:\/\/(.*?)\@/',$input_id,$match)){
        $uuid_or_username_to_search = $match[1];
    }elseif(preg_match('/^trojan:\/\/(.*?)\@/',$input_id,$match)){
        $uuid_or_username_to_search = $match[1];
    }

    $uuid_or_username_to_search = htmlspecialchars(stripslashes(trim($uuid_or_username_to_search)));
    $config_link_input = htmlspecialchars(stripslashes(trim($config_link_input))); // Original input for link matching

    $stmt_servers = $connection->prepare("SELECT * FROM `server_config`");
    $stmt_servers->execute();
    $serversList = $stmt_servers->get_result();
    $stmt_servers->close();

    $found = false;

    while($server_row = $serversList->fetch_assoc()){
        $serverId = $server_row['id'];
        $serverType = $server_row['type'];

        if($serverType == "marzban"){
            $user_obj_marzban = null;
            $matched_by_direct_lookup = false;

            // Attempt 1: Direct lookup by username (if input wasn't a full link but a potential username)
            if ($uuid_or_username_to_search == $config_link_input && !preg_match('/:\/\//', $config_link_input)) {
                $directUserResponse = getMarzbanUser($serverId, $uuid_or_username_to_search);
                if ($directUserResponse && isset($directUserResponse->success) && $directUserResponse->success && isset($directUserResponse->obj)) {
                    $user_obj_marzban = $directUserResponse->obj;
                    $matched_by_direct_lookup = true;
                }
            }

            // Attempt 2: Scan all users if direct lookup failed or input was likely a link
            if (!$matched_by_direct_lookup) {
                $allUsersResponse = getMarzbanJson($serverId, "/api/users"); // Get all users from Marzban panel
                if ($allUsersResponse && isset($allUsersResponse->success) && $allUsersResponse->success && isset($allUsersResponse->obj->users)) {
                    foreach ($allUsersResponse->obj->users as $currentUser) {
                        $is_current_match = false;
                        // Check username if $uuid_or_username_to_search was a plain username
                        if ($currentUser->username == $uuid_or_username_to_search) {
                            $is_current_match = true;
                        }
                        // Check if original input link is part of this user's links
                        if (!$is_current_match && isset($currentUser->links) && is_array($currentUser->links)) {
                            foreach ($currentUser->links as $link) {
                                if (strpos($link, $config_link_input) !== false) {
                                    $is_current_match = true;
                                    break;
                                }
                            }
                        }
                        // Check subscription URL
                        if (!$is_current_match && isset($currentUser->subscription_url) && strpos($currentUser->subscription_url, $config_link_input) !== false) {
                            $is_current_match = true;
                        }
                        if ($is_current_match) {
                            $user_obj_marzban = $currentUser;
                            break;
                        }
                    }
                }
            }

            if (isset($user_obj_marzban)) {
                $found = true;
                $isMarzban = true;
                $GLOBALS['remark'] = $user_obj_marzban->username;
                $GLOBALS['total'] = $user_obj_marzban->data_limit != 0 ? sumerize($user_obj_marzban->data_limit) : "نامحدود";
                $GLOBALS['totalUsed'] = sumerize($user_obj_marzban->used_traffic);
                $GLOBALS['state'] = $user_obj_marzban->status == "active" ? ($buttonValues['active']) : ($buttonValues['deactive']);
                $GLOBALS['expiryTime'] = $user_obj_marzban->expire != 0 ? jdate("Y-m-d H:i:s", $user_obj_marzban->expire) : "نامحدود";
                $leftMb_val = $user_obj_marzban->data_limit != 0 ? ($user_obj_marzban->data_limit - $user_obj_marzban->used_traffic) : "نامحدود";
                $GLOBALS['leftMb'] = is_numeric($leftMb_val) ? ($leftMb_val < 0 ? sumerize(0) : sumerize($leftMb_val)) : "نامحدود";
                $expiryDay_val = $user_obj_marzban->expire != 0 ? floor(($user_obj_marzban->expire - time()) / (60 * 60 * 24)) : "نامحدود";
                $GLOBALS['expiryDay'] = is_numeric($expiryDay_val) ? ($expiryDay_val < 0 ? 0 : $expiryDay_val) : "نامحدود";
                $GLOBALS['upload'] = "-";
                $GLOBALS['download'] = "-";
                break;
            }
        } else { // X-UI Panel
            $apiResponse = getJson($serverId);
            if($apiResponse && isset($apiResponse->success) && $apiResponse->success && isset($apiResponse->obj)){
                $inboundsList = $apiResponse->obj;
                foreach($inboundsList as $inbound_data){
                    $client_details_found_in_inbound = false;

                    // Case 1: Inbound with clientStats (multiple clients under one inbound setting)
                    if(isset($inbound_data->clientStats) && is_array($inbound_data->clientStats) && !empty($inbound_data->clientStats)){
                        $clientsSettingsArray = json_decode($inbound_data->settings, true);
                        $clientsSettings = $clientsSettingsArray['clients'] ?? [];

                        $clientEmailToSearch = null;
                        foreach($clientsSettings as $clientSetting){
                            if(isset($clientSetting['id']) && $clientSetting['id'] == $uuid_or_username_to_search){ // $uuid_or_username_to_search is parsed UUID
                                $clientEmailToSearch = $clientSetting['email'] ?? null;
                                break;
                            }
                        }

                        if($clientEmailToSearch){
                            foreach($inbound_data->clientStats as $stat){
                                if(isset($stat->email) && $stat->email == $clientEmailToSearch){
                                    $client_details_found_in_inbound = true;
                                    $GLOBALS['remark'] = $stat->email;
                                    $GLOBALS['upload'] = sumerize2($stat->up);
                                    $GLOBALS['download'] = sumerize2($stat->down);
                                    $GLOBALS['totalUsed'] = sumerize2($stat->up + $stat->down);

                                    $stat_total = $stat->total ?? 0;
                                    $inbound_main_total = $inbound_data->total ?? 0;
                                    $actual_total = $stat_total ?: $inbound_main_total;
                                    $GLOBALS['total'] = $actual_total != 0 ? sumerize2($actual_total) : "نامحدود";

                                    $stat_expiry = $stat->expiryTime ?? 0;
                                    $inbound_main_expiry = $inbound_data->expiryTime ?? 0;
                                    $actual_expiry = $stat_expiry ?: $inbound_main_expiry;
                                    $GLOBALS['expiryTime'] = $actual_expiry != 0 ? jdate("Y-m-d H:i:s", substr($actual_expiry, 0, -3)) : "نامحدود";

                                    $leftMb_val = $actual_total != 0 ? ($actual_total - ($stat->up + $stat->down)) : "نامحدود";
                                    $GLOBALS['leftMb'] = is_numeric($leftMb_val) ? ($leftMb_val < 0 ? sumerize2(0) : sumerize2($leftMb_val)) : "نامحدود";

                                    $expiryDay_val = $actual_expiry != 0 ? floor((substr($actual_expiry, 0, -3) - time()) / (60 * 60 * 24)) : "نامحدود";
                                    $GLOBALS['expiryDay'] = is_numeric($expiryDay_val) ? ($expiryDay_val < 0 ? 0 : $expiryDay_val) : "نامحدود";

                                    $GLOBALS['state'] = ($stat->enable ?? $inbound_data->enable ?? false) ? ($buttonValues['active']) : ($buttonValues['deactive']);
                                    break;
                                }
                            }
                        }
                    }

                    // Case 2: Direct client on an inbound (no clientStats or clientStats not relevant/found, or previous search failed)
                    if(!$client_details_found_in_inbound && isset($inbound_data->settings)) {
                         $settingsDecoded = json_decode($inbound_data->settings);
                         if($settingsDecoded !== null && isset($settingsDecoded->clients) && isset($settingsDecoded->clients[0])){
                             $client = $settingsDecoded->clients[0];
                             if((isset($client->id) && $client->id == $uuid_or_username_to_search) || (isset($client->password) && $client->password == $uuid_or_username_to_search)){
                                $client_details_found_in_inbound = true;
                                $GLOBALS['remark'] = $inbound_data->remark;
                                $GLOBALS['upload'] = sumerize2($inbound_data->up);
                                $GLOBALS['download'] = sumerize2($inbound_data->down);
                                $GLOBALS['totalUsed'] = sumerize2($inbound_data->up + $inbound_data->down);
                                $GLOBALS['total'] = $inbound_data->total != 0 ? sumerize2($inbound_data->total) : "نامحدود";
                                $GLOBALS['expiryTime'] = $inbound_data->expiryTime != 0 ? jdate("Y-m-d H:i:s", substr($inbound_data->expiryTime, 0, -3)) : "نامحدود";
                                $leftMb_val = $inbound_data->total != 0 ? ($inbound_data->total - ($inbound_data->up + $inbound_data->down)) : "نامحدود";
                                $GLOBALS['leftMb'] = is_numeric($leftMb_val) ? ($leftMb_val < 0 ? sumerize2(0) : sumerize2($leftMb_val)) : "نامحدود";
                                $expiryDay_val = $inbound_data->expiryTime != 0 ? floor((substr($inbound_data->expiryTime, 0, -3) - time()) / (60 * 60 * 24)) : "نامحدود";
                                $GLOBALS['expiryDay'] = is_numeric($expiryDay_val) ? ($expiryDay_val < 0 ? 0 : $expiryDay_val) : "نامحدود";
                                $GLOBALS['state'] = ($inbound_data->enable ?? false) ? ($buttonValues['active']) : ($buttonValues['deactive']);
                             }
                         }
                    }

                    if($client_details_found_in_inbound){
                        $found = true;
                        $isMarzban = false;
                        break;
                    }
                }
            }
        }
        if($found) break;
    }

    if(!$found){
        form("اطلاعات وارد شده اشتباه می باشد");
    }else{
        showForm("configInfo");
    }
}
else{
    showForm("unknown");
}

if (!function_exists('sumerize')) {
    function sumerize($size) {
        if ($size == 0) return "0 GB";
        $gb = $size / 1073741824;
        if ($gb < 0.01 && $gb > 0) return round($size / 1048576, 2) . " MB";
        return round($gb, 2) . " GB";
    }
}
if (!function_exists('sumerize2')) {
    function sumerize2($size) {
        if ($size == 0) return "0 GB";
        $gb = $size / 1073741824;
        if ($gb < 0.01 && $gb > 0) return round($size / 1048576, 2) . " MB";
        return round($gb, 2) . " GB";
    }
}

function showForm($type){
    global $remark, $isMarzban, $totalUsed, $state, $upload, $download, $total, $leftMb, $expiryTime, $expiryDay;
    $buttonValues = $GLOBALS['buttonValues'] ?? ['active' => "فعال 🟢", 'deactive' => "غیر فعال 🔴"];
    ?>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php
            if($type=="unknown") echo "جستجوی اطلاعات کانفیگ";
            elseif ($type=="configInfo") echo "نتیجه اطلاعات کانفیگ";
            else echo "اطلاعات";
            ?></title>
        <link type="text/css" href="assets/webconf.css" rel="stylesheet" />
    </head>
    <body style="background: #f0f2f5;">
    <?php if ($type=="configInfo"){
        $download_percent = 0; $upload_percent = 0; $leftMb_percent = 0; $totalUsed_percent = 0;
        $total_numeric = 0;
        if ($GLOBALS['total'] != "نامحدود" && is_string($GLOBALS['total'])) {
            // Extract numeric part for calculations
            preg_match('/[\\d\\.]+/', $GLOBALS['total'], $matches_total);
            if(isset($matches_total[0])) $total_numeric = floatval($matches_total[0]);
            if(strpos($GLOBALS['total'], "MB") !== false) $total_numeric /= 1024; // Convert MB to GB for consistency if needed
        }

        if ($GLOBALS['total'] != "نامحدود" && $total_numeric > 0) {
            if (!$isMarzban && $GLOBALS['download'] != 'N/A' && is_string($GLOBALS['download'])) {
                 preg_match('/[\\d\\.]+/', $GLOBALS['download'], $matches_dl);
                 if(isset($matches_dl[0])){
                    $download_val = floatval($matches_dl[0]);
                    if(strpos($GLOBALS['download'], "MB") !== false) $download_val /= 1024;
                    $download_percent = round(100 * $download_val / $total_numeric, 2);
                 }
            }
            if (!$isMarzban && $GLOBALS['upload'] != 'N/A' && is_string($GLOBALS['upload'])) {
                 preg_match('/[\\d\\.]+/', $GLOBALS['upload'], $matches_ul);
                 if(isset($matches_ul[0])){
                    $upload_val = floatval($matches_ul[0]);
                    if(strpos($GLOBALS['upload'], "MB") !== false) $upload_val /= 1024;
                    $upload_percent = round(100 * $upload_val / $total_numeric, 2);
                 }
            }
            if ($GLOBALS['leftMb'] != 'N/A' && is_string($GLOBALS['leftMb'])) {
                 preg_match('/[\\d\\.]+/', $GLOBALS['leftMb'], $matches_left);
                 if(isset($matches_left[0])){
                    $leftMb_val_num = floatval($matches_left[0]);
                    if(strpos($GLOBALS['leftMb'], "MB") !== false) $leftMb_val_num /= 1024;
                    $leftMb_percent = round(100 * $leftMb_val_num / $total_numeric, 2);
                 }
            }
            if ($isMarzban && $GLOBALS['totalUsed'] != 'N/A' && is_string($GLOBALS['totalUsed'])) {
                 preg_match('/[\\d\\.]+/', $GLOBALS['totalUsed'], $matches_tu);
                 if(isset($matches_tu[0])){
                    $totalUsed_val = floatval($matches_tu[0]);
                     if(strpos($GLOBALS['totalUsed'], "MB") !== false) $totalUsed_val /= 1024;
                    $totalUsed_percent = round(100 * $totalUsed_val / $total_numeric, 2);
                 }
            }
        } elseif ($GLOBALS['total'] == "نامحدود") {
            $leftMb_percent = 100;
        }

        // Clamp percentages
        $download_percent = max(0, min(100, $download_percent));
        $upload_percent = max(0, min(100, $upload_percent));
        $leftMb_percent = max(0, min(100, $leftMb_percent));
        $totalUsed_percent = max(0, min(100, $totalUsed_percent));

        $state_color_class = (strpos($GLOBALS['state'], "فعال") !== false) ? "status-active" : "status-inactive";
        ?>
        <div class="container">
            <div id="contact" class="contactw">
                <p class="title">اطلاعات کانفیگ: <span class="remark-name"><?php echo htmlspecialchars($GLOBALS['remark']);?></span></p>
                <p class="status <?php echo $state_color_class; ?>">وضعیت: <?php echo htmlspecialchars($GLOBALS['state']);?></p>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="label"><?php echo $isMarzban ? "مصرف کل (دانلود + آپلود)" : "میزان دانلود";?>:</span>
                        <span class="value"><?php echo htmlspecialchars($isMarzban ? $GLOBALS['totalUsed'] : $GLOBALS['download']);?></span>
                        <?php if ($GLOBALS['total'] != "نامحدود"): ?>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $isMarzban ? $totalUsed_percent : $download_percent; ?>%;"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if(!$isMarzban): ?>
                    <div class="info-item">
                        <span class="label">میزان آپلود:</span>
                        <span class="value"><?php echo htmlspecialchars($GLOBALS['upload']);?></span>
                        <?php if ($GLOBALS['total'] != "نامحدود"): ?>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $upload_percent; ?>%;"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="label">حجم باقیمانده:</span>
                        <span class="value"><?php echo htmlspecialchars($GLOBALS['leftMb']);?></span>
                         <?php if ($GLOBALS['total'] != "نامحدود"): ?>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $leftMb_percent; ?>%; background-color: #4caf50;"></div>
                        </div>
                        <?php endif; ?>
                    </div>
                     <div class="info-item">
                        <span class="label">حجم کل:</span>
                        <span class="value"><?php echo htmlspecialchars($GLOBALS['total']);?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">تاریخ انقضا:</span>
                        <span class="value"><?php echo htmlspecialchars($GLOBALS['expiryTime']);?></span>
                    </div>
                    <div class="info-item">
                        <span class="label">روزهای باقیمانده:</span>
                        <span class="value"><?php echo htmlspecialchars($GLOBALS['expiryDay'] . ($GLOBALS['expiryDay'] != "نامحدود" && is_numeric($GLOBALS['expiryDay']) ? " روز" : ""));?></span>
                    </div>
                </div>
                <p class="footer-text">Made with 🖤 by <a target="_blank" href="https://github.com/wizwizdev/wizwizxui-timebot">WizWiz</a></p>
            </div>
        </div>

    <?php }
    elseif($type=="unknown"){ ?>
        <div class="container">
            <form id="contact" class="contactw" action="search.php" method="get">
                <h3 style="margin:20px 0; color: #333; font-size: 20px; text-align: center;">اطلاعات کانفیگ خود را جستجو کنید</h3>
                <fieldset>
                    <input placeholder="لینک اتصال یا UUID/نام کاربری کانفیگ" type="text" id="id" name="id" autocomplete="off" required autofocus>
                </fieldset>
                <fieldset>
                    <button class="search" type="submit" id="contact-submit" data-submit="...درحال جستجو">جستجو</button>
                </fieldset>
                <p class="footer-text">Made with 🖤 by <a target="_blank" href="https://github.com/wizwizdev/wizwizxui-timebot">WizWiz</a></p>
            </form>
        </div>
    <?php } ?>
    </body>
    </html>
    <?php
}

function form($msg, $error = true){
    ?>
    <html dir="rtl" lang="fa">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>وضعیت</title>
        <link type="text/css" href="assets/webconf.css" rel="stylesheet" />
    </head>
    <body style="background: #f0f2f5;">
        <div class="container">
            <div id="contact" class="contactw" style="text-align: center; padding: 40px 20px;">
                <?php if ($error == true){ ?>
                <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="status-icon error-icon" stroke-width="1.5" width="80" height="80" style="margin-bottom: 20px;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>
                <?php } else { ?>
                 <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="status-icon success-icon" stroke-width="1.5" width="80" height="80" style="margin-bottom: 20px;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <?php } ?>
                <p style="font-size: 18px; color: #333; margin-bottom:30px;"><?php echo htmlspecialchars($msg); ?></p>
                <a href="search.php" class="button-link">بازگشت به صفحه جستجو</a>
                 <p class="footer-text" style="margin-top: 40px;">Made with 🖤 by <a target="_blank" href="https://github.com/wizwizdev/wizwizxui-timebot">WizWiz</a></p>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>
