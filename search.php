<?php
if (!file_exists("baseInfo.php") || !file_exists("config.php")) {
    form("فایل های مورد نیاز یافت نشد");
    exit();
}

require "baseInfo.php";
require "config.php"; // Assuming this now contains the updated getJson and other functions
include "jdf.php";

if (isset($_REQUEST['id'])) {
    $config_identifier = $_REQUEST['id']; // This can be a direct UUID, or a full config link

    $client_uuid_or_password = null;
    $marzbanText = null; // For Marzban link specific part

    if (preg_match('/^vmess:\/\/(.*)/', $config_identifier, $match)) {
        $jsonDecode = json_decode(base64_decode($match[1]), true);
        $client_uuid_or_password = isset($jsonDecode['id']) ? $jsonDecode['id'] : null;
        $marzbanText = $match[1]; // Used for Marzban specific search
    } elseif (preg_match('/^vless:\/\/(.*?)\@/', $config_identifier, $match)) {
        $client_uuid_or_password = $match[1];
        $marzbanText = $match[1];
    } elseif (preg_match('/^trojan:\/\/(.*?)\@/', $config_identifier, $match)) {
        $client_uuid_or_password = $match[1];
        $marzbanText = $match[1];
    } elseif (preg_match('/[a-f0-9]{8}\-[a-f0-9]{4}\-4[a-f0-9]{3}\-(8|9|a|b)[a-f0-9]{3}\-[a-f0-9]{12}/i', $config_identifier) || preg_match('/^[a-zA-Z0-9]{5,255}/', $config_identifier) ) { // Adjusted length for passwords
        $client_uuid_or_password = $config_identifier;
        $marzbanText = $config_identifier; // For Marzban, username can be the identifier
    } else {
        form("فرمت شناسه کانفیگ معتبر نمی باشد. لطفا لینک کامل یا UUID/پسورد را وارد کنید.");
        exit();
    }

    $client_uuid_or_password = htmlspecialchars(stripslashes(trim($client_uuid_or_password)));

    $stmt = $connection->prepare("SELECT * FROM `server_config`");
    $stmt->execute();
    $serversList = $stmt->get_result();
    $stmt->close();

    $found = false;
    $isMarzban = false;
    $config_display_info = [];

    while ($server_row = $serversList->fetch_assoc()) {
        $serverId = $server_row['id'];
        $serverType = $server_row['type'];

        if ($serverType == "marzban") {
            // Marzban logic (assumed to be mostly unchanged as it uses a different API interaction)
            $tokenResponse = getMarzbanToken($serverId);
            if (!isset($tokenResponse->access_token)) {
                // Could log this error or inform admin, but for user, skip this server
                continue;
            }
            $usersListResponse = getMarzbanJson($serverId, $tokenResponse);
            if (isset($usersListResponse->users) && is_array($usersListResponse->users)) {
                foreach ($usersListResponse->users as $config) {
                    if (isset($config->username) && $config->username === $client_uuid_or_password) { // Marzban uses username
                        $found = true;
                        $isMarzban = true;
                        $remark = $config->username;
                        $total = $config->data_limit != 0 ? sumerize($config->data_limit) : "نامحدود";
                        $totalRaw = $config->data_limit;
                        $usedTrafficRaw = $config->used_traffic;
                        $totalUsed = sumerize($usedTrafficRaw);
                        $state = $config->status == "active" ? "فعال 🟢" : "غیر فعال 🔴";
                        $expiryTimeEpoch = $config->expire;
                        $expiryTime = $expiryTimeEpoch != 0 ? jdate("Y-m-d H:i:s", $expiryTimeEpoch) : "نامحدود";
                        $leftMbRaw = $totalRaw != 0 ? $totalRaw - $usedTrafficRaw : INF; // INF for unlimited

                        if (is_finite($leftMbRaw)) {
                            $leftMb = $leftMbRaw < 0 ? sumerize(0) : sumerize($leftMbRaw);
                        } else {
                            $leftMb = "نامحدود";
                        }

                        $expiryDay = $expiryTimeEpoch != 0 ? floor(($expiryTimeEpoch - time()) / (60 * 60 * 24)) : "نامحدود";
                        if (is_numeric($expiryDay) && $expiryDay < 0) $expiryDay = 0;

                        // For progress bar percentages
                        $downloadPercent = ($totalRaw != 0 && is_finite($totalRaw)) ? round(100 * $usedTrafficRaw / $totalRaw, 2) : 0; // Marzban combines up/down
                        $uploadPercent = 0; // Not separate in Marzban
                        $leftPercent = ($totalRaw != 0 && is_finite($totalRaw)) ? round(100 * $leftMbRaw / $totalRaw, 2) : 100;
                        if ($leftPercent < 0) $leftPercent = 0;
                        if ($leftPercent > 100 && is_finite($totalRaw)) $leftPercent = 100; // Cap at 100 if not unlimited


                        $config_display_info = compact('remark', 'isMarzban', 'totalUsed', 'state', 'upload', 'download', 'total', 'leftMb', 'expiryTime', 'expiryDay', 'downloadPercent', 'uploadPercent', 'leftPercent');
                        break 2;
                    }
                }
            }
        } else { // X-UI type panels
            $apiResponse = getJson($serverId);
            if ($apiResponse && isset($apiResponse->success) && $apiResponse->success && isset($apiResponse->obj)) {
                $inbounds = $apiResponse->obj;
                if (is_array($inbounds)) {
                    foreach ($inbounds as $inbound) {
                        $inboundSettingsStr = $inbound->settings;
                        $inboundSettings = json_decode($inboundSettingsStr, true);

                        if (isset($inboundSettings['clients']) && is_array($inboundSettings['clients'])) {
                            foreach ($inboundSettings['clients'] as $clientIndex => $client) {
                                $currentClientId = isset($client['id']) ? $client['id'] : (isset($client['password']) ? $client['password'] : null);
                                if ($currentClientId === $client_uuid_or_password) {
                                    $found = true;
                                    $isMarzban = false;

                                    $clientEmail = isset($client['email']) ? $client['email'] : $inbound->remark; // Use client email if available, else inbound remark
                                    $remark = $clientEmail;

                                    // Initialize with client-specific settings from the 'clients' array
                                    $clientTotalGB = isset($client['totalGB']) ? $client['totalGB'] : 0;
                                    $clientExpiryTime = isset($client['expiryTime']) ? $client['expiryTime'] : 0;
                                    $clientEnabled = isset($client['enable']) ? $client['enable'] : $inbound->enable; // Fallback to inbound enable state

                                    // Check clientStats for potentially more up-to-date traffic and expiry
                                    $upRaw = 0; $downRaw = 0; $totalRaw = $clientTotalGB; $actualExpiryTime = $clientExpiryTime;

                                    if (isset($inbound->clientStats) && is_array($inbound->clientStats)) {
                                        foreach ($inbound->clientStats as $stat) {
                                            if (isset($stat->email) && $stat->email == $clientEmail) {
                                                $upRaw = $stat->up;
                                                $downRaw = $stat->down;
                                                // If clientStat total/expiry is set, it overrides the one in settings
                                                if ($stat->total != 0) $totalRaw = $stat->total;
                                                if ($stat->expiryTime != 0) $actualExpiryTime = $stat->expiryTime;
                                                // Client's 'enable' in settings takes precedence, but clientStats 'enable' can also be an indicator
                                                // For simplicity, we'll use the 'enable' from the client's definition in 'settings' array
                                                break;
                                            }
                                        }
                                    }
                                    // If clientStats didn't provide total/expiry, and client settings also didn't, fallback to inbound
                                    if ($totalRaw == 0 && $inbound->total != 0) $totalRaw = $inbound->total;
                                    if ($actualExpiryTime == 0 && $inbound->expiryTime != 0) $actualExpiryTime = $inbound->expiryTime;


                                    $upload = sumerize2($upRaw);
                                    $download = sumerize2($downRaw);
                                    $totalUsed = sumerize2($upRaw + $downRaw);
                                    $total = $totalRaw != 0 ? sumerize2($totalRaw) : "نامحدود";

                                    $leftMbRaw = $totalRaw != 0 ? ($totalRaw - $upRaw - $downRaw) : INF;
                                    if (is_finite($leftMbRaw)) {
                                        $leftMb = $leftMbRaw < 0 ? sumerize2(0) : sumerize2($leftMbRaw);
                                    } else {
                                        $leftMb = "نامحدود";
                                    }

                                    $expiryTime = $actualExpiryTime != 0 ? jdate("Y-m-d H:i:s", substr($actualExpiryTime, 0, -3)) : "نامحدود";
                                    $expiryDay = $actualExpiryTime != 0 ? floor((substr($actualExpiryTime, 0, -3) - time()) / (60 * 60 * 24)) : "نامحدود";
                                    if (is_numeric($expiryDay) && $expiryDay < 0) $expiryDay = 0;

                                    $state = $clientEnabled == true ? "فعال 🟢" : "غیر فعال 🔴";

                                    // For progress bar percentages
                                    $downloadPercent = ($totalRaw != 0 && is_finite($totalRaw)) ? round(100 * $downRaw / $totalRaw, 2) : 0;
                                    $uploadPercent = ($totalRaw != 0 && is_finite($totalRaw)) ? round(100 * $upRaw / $totalRaw, 2) : 0;
                                    $leftPercent = ($totalRaw != 0 && is_finite($totalRaw)) ? round(100 * $leftMbRaw / $totalRaw, 2) : 100;
                                    if ($leftPercent < 0) $leftPercent = 0;
                                    if ($leftPercent > 100 && is_finite($totalRaw)) $leftPercent = 100;

                                    $config_display_info = compact('remark', 'isMarzban', 'totalUsed', 'state', 'upload', 'download', 'total', 'leftMb', 'expiryTime', 'expiryDay', 'downloadPercent', 'uploadPercent', 'leftPercent');
                                    break 2; // Found client
                                }
                            }
                        }
                    }
                }
            } else {
                // Optional: Log API error for this server: $apiResponse->msg
                // form("خطا در ارتباط با سرور: " . $server_row['title'] . " - " . $apiResponse->msg);
                // continue; // Skip to next server if one fails
            }
        }
    }

    if (!$found) {
        form("کانفیگ با این مشخصات یافت نشد یا در حال حاضر در دسترس نیست.");
    } else {
        // Pass all necessary variables to showForm
        showForm("configInfo", $config_display_info);
    }
} else {
    showForm("unknown", []);
}

function showForm($type, $config_data = []){ // Added $config_data parameter
    global $buttonValues; // Assuming $buttonValues is for button text, not directly used in this part of HTML

    // Extract variables for easier use in HTML, with defaults
    extract(array_merge([
        'remark' => 'N/A',
        'isMarzban' => false,
        'totalUsed' => '0',
        'state' => 'N/A',
        'upload' => '0',
        'download' => '0',
        'total' => 'N/A',
        'leftMb' => 'N/A',
        'expiryTime' => 'N/A',
        'expiryDay' => 'N/A',
        'downloadPercent' => 0,
        'uploadPercent' => 0,
        'leftPercent' => 0
    ], $config_data));

    // Ensure percentages are capped for display
    $downloadPercent = max(0, min(100, $downloadPercent));
    $uploadPercent = max(0, min(100, $uploadPercent));
    $leftPercent = max(0, min(100, $leftPercent));

    // Convert numeric total to GB for display if it's not "نامحدود"
    $totalDisplay = $total;
    if(is_numeric($total) && $total != "نامحدود") $totalDisplay = $total . "GB";


    ?>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?php if($type=="unknown") echo "جستجوی اطلاعات کانفیگ";
            elseif ($type=="configInfo") echo "اطلاعات کانفیگ: " . htmlspecialchars($remark); // Use htmlspecialchars for safety
            ?></title>
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <link type="text/css" href="assets/webconf.css" rel="stylesheet" />
    </head>
    <body style="background: <?php if($type=="unknown" || $state == "فعال 🟢") echo "#f7f0f5"; else echo "#FFDDDD"; // Lighter red for inactive ?>;">
    <?php if ($type=="configInfo"){ ?>
        <div class="container" style="">
            <form id="contact" class="contactw">
                <br>
                <p style="font-size:22px;font-weight: bold;color:#1d3557;font-family:iransans !important;">اطلاعات کانفیگ: <?php echo htmlspecialchars($remark);?></p>
                <p style="font-size:18px;font-weight: bold;color:<?php echo ($state == "فعال 🟢" ? "#28a745" : "#dc3545");?>;margin-top:15px;">وضعیت: <?php echo $state;?></p>
                <br>
                <div class="mainform" >
                    <div>
                    <svg xmlns="http://www.w3.org/2000/svg" id="Capa_1" x="0px" y="0px" viewBox="0 0 512 512" style="margin-left: 6px;enable-background:new 0 0 512 512;" xml:space="preserve" width="20" height="20">
                        <g>
                            <path d="M210.731,386.603c24.986,25.002,65.508,25.015,90.51,0.029c0.01-0.01,0.019-0.019,0.029-0.029l68.501-68.501   c7.902-8.739,7.223-22.23-1.516-30.132c-8.137-7.357-20.527-7.344-28.649,0.03l-62.421,62.443l0.149-329.109   C277.333,9.551,267.782,0,256,0l0,0c-11.782,0-21.333,9.551-21.333,21.333l-0.192,328.704L172.395,288   c-8.336-8.33-21.846-8.325-30.176,0.011c-8.33,8.336-8.325,21.846,0.011,30.176L210.731,386.603z"/>
                            <path d="M490.667,341.333L490.667,341.333c-11.782,0-21.333,9.551-21.333,21.333V448c0,11.782-9.551,21.333-21.333,21.333H64   c-11.782,0-21.333-9.551-21.333-21.333v-85.333c0-11.782-9.551-21.333-21.333-21.333l0,0C9.551,341.333,0,350.885,0,362.667V448   c0,35.346,28.654,64,64,64h384c35.346,0,64-28.654,64-64v-85.333C512,350.885,502.449,341.333,490.667,341.333z"/>
                        </g>
                    </svg>
                        <p style="font-size:16px"><?php if($isMarzban) echo "مصرف کل"; else echo "دانلود";?></p>
                        <div class="progress-bar" style="display:flex; background: radial-gradient(closest-side, #F9F9F9 79%, transparent 80% 100%),conic-gradient(<?php
                            $perc = $isMarzban ? $downloadPercent : $downloadPercent; // Marzban total used might be $downloadPercent here
                            if($perc <= 50) echo "#04a777 "; elseif($perc <= 70) echo "yellow "; else echo "red "; echo $perc . "%";
                        ?>, #e2eafc 0);">
                        <?php echo ($isMarzban ? $totalUsed : $download) . ($isMarzban && $totalUsed !=="نامحدود" ? "GB" : "");?></div>
                    </div>
                    <?php if(!$isMarzban){?>
                        <div style="margin-right:50px;">
                            <svg style="margin-left: 6px" xmlns="http://www.w3.org/2000/svg" id="Layer_1" data-name="Layer 1" viewBox="0 0 24 24" width="20" height="20"><path d="M23.9,11.437A12,12,0,0,0,0,13a11.878,11.878,0,0,0,3.759,8.712A4.84,4.84,0,0,0,7.113,23H16.88a4.994,4.994,0,0,0,3.509-1.429A11.944,11.944,0,0,0,23.9,11.437Zm-4.909,8.7A3,3,0,0,1,16.88,21H7.113a2.862,2.862,0,0,1-1.981-.741A9.9,9.9,0,0,1,2,13,10.014,10.014,0,0,1,5.338,5.543,9.881,9.881,0,0,1,11.986,3a10.553,10.553,0,0,1,1.174.066,9.994,9.994,0,0,1,5.831,17.076ZM7.807,17.285a1,1,0,0,1-1.4,1.43A8,8,0,0,1,12,5a8.072,8.072,0,0,1,1.143.081,1,1,0,0,1,.847,1.133.989.989,0,0,1-1.133.848,6,6,0,0,0-5.05,10.223Zm12.112-5.428A8.072,8.072,0,0,1,20,13a7.931,7.931,0,0,1-2.408,5.716,1,1,0,0,1-1.4-1.432,5.98,5.98,0,0,0,1.744-5.141,1,1,0,0,1,1.981-.286Zm-5.993.631a2.033,2.033,0,1,1-1.414-1.414l3.781-3.781a1,1,0,1,1,1.414,1.414Z"/></svg>
                            <p style="font-size:16px; font-family:iransans !important;">آپلود</p>
                            <div class="progress-bar" style="display:flex; background: radial-gradient(closest-side, #F9F9F9 79%, transparent 80% 100%),conic-gradient(<?php
                                if($uploadPercent <= 30) echo "#f48c06 "; elseif($uploadPercent < 50) echo "yellow "; else echo "#ed254e ";  echo $uploadPercent . "%";
                            ?>, #e2eafc 0);">
                            <?php echo $upload . ($upload !=="نامحدود" ? "GB" : "");?></div>
                        </div>
                    <?php }?>
                </div>
                <div class="mainform" style="margin-top:50px;">
                    <div style="margin-left: 6px">
                        <svg style="margin-left: 6px" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M12 2C17.52 2 22 6.48 22 12C22 17.52 17.52 22 12 22C6.48 22 2 17.52 2 12C2 6.48 6.48 2 12 2ZM12 4C7.58 4 4 7.58 4 12C4 16.42 7.58 20 12 20C16.42 20 20 16.42 20 12C20 7.58 16.42 4 12 4ZM12.75 7V13H17V11.5H14.25V7H12.75Z"></path></svg>
                        <p style="font-size:16px; font-family:iransans !important;">حجم باقیمانده</p>
                        <div class="progress-bar" style="display:flex; background: radial-gradient(closest-side, #F9F9F9 79%, transparent 80% 100%),conic-gradient(<?php
                            if($leftPercent <= 30) echo "red "; elseif($leftPercent < 50) echo "yellow "; else echo "#4CAF50 "; echo $leftPercent . "%"; // Green for good amount left
                        ?>, #e2eafc 0);">
                        <?php echo $leftMb . ($leftMb !=="نامحدود" ? "GB" : "");?></div>
                    </div>
                    <div style="margin-right:50px;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><path d="M19 3H18V1H16V3H8V1H6V3H5C3.89543 3 3 3.89543 3 5V19C3 20.1046 3.89543 21 5 21H19C20.1046 21 21 20.1046 21 19V5C21 3.89543 20.1046 3 19 3ZM19 5L19.001 19H5V5H19ZM12 10C10.3431 10 9 11.3431 9 13C9 14.6569 10.3431 16 12 16C13.6569 16 15 14.6569 15 13C15 11.3431 13.6569 10 12 10Z"></path></svg>
                        <p style="font-size:16px">حجم کلی</p>
                        <div class="progress-bar" style="display:flex; background: radial-gradient(closest-side, #F9F9F9 79%, transparent 80% 100%),conic-gradient(#467599 100%, #467599 0);">
                        <?php echo $totalDisplay;?></div>
                    </div>
                </div>
                <div class="container">
                    <p class="tarikh" style="font-size:14px;margin-top:10px; color: #1d3557;">
                       تاریخ انقضا: <span><?php echo $expiryTime;?></span>
                    </p>
                    <p class="tarikh" style="font-size:14px;margin-top:5px; color: #1d3557;">
                       روز باقیمانده: <span style="color: <?php echo (is_numeric($expiryDay) && $expiryDay < 7 ? 'red' : '#1d3557'); ?>;"><?php echo $expiryDay . ($expiryDay !=="نامحدود" ? " روز" : "");?></span>
                    </p>
                </div>
                <p style="font-size:10px">Made with 🖤 in <a target="_blank" href="https://github.com/wizwizdev/wizwizxui-timebot">wizwiz</a></p>
            </form>
        </div>

    <?php }
    elseif($type=="unknown"){ ?>

        <div class="container">
            <form id="contact" action="search.php" method="get">
                <h3 style="margin:20px">لطفا شناسه کانفیگ خود را وارد کنید</h3>
                <fieldset>
                    <input placeholder="مثال: 12345678-abcd-1234-abcd-1234567890ab یا لینک کامل کانفیگ" type="text"  id="id" name="id" autocomplete="off" required >
                </fieldset>
                <fieldset>
                    <button class="search" type="submit">جستجو</button>
                </fieldset>
                <p style="font-size:13px">Made with 🖤 in <a target="_blank" href="https://github.com/wizwizdev/wizwizxui-timebot">wizwiz</a></p>
            </form>
        </div>
        <br>
        <br>
    <?php } ?>
    </body>
    </html>
    <?php
}
function form($msg, $error = true){ // Renamed $cancelKey to avoid conflict, though not used here
    global $buttonValues; // Assuming $buttonValues might be used in the future or for consistency
    ?>

    <html dir="rtl" lang="fa">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>خطا</title>
        <link type="text/css" href="assets/webconf.css" rel="stylesheet" />
    </head>
    <body>
    <div id="__next">
        <section class="ant-layout1 PayPing-layout1">
            <main>
                <div class="justify-center align-center w-100">
                    <div class="div1">
                        <div class="div2">
                            <?php if ($error == true){ ?> <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" class="PayPing-icon" stroke-width="1" width="100">
                                <circle cx="12" cy="12" r="11"></circle>
                                <path d="M15.3 8.7l-6.6 6.6M8.7 8.7l6.6 6.6"></path>
                            </svg>
                            <?php }?>
                            <div style="padding: 40px 30px; font-family: iransans, sans-serif; font-size: 16px;" > <?php echo htmlspecialchars($msg); // Sanitize output ?></div>
                             <button onclick="window.location.href='search.php'" style="font-family: iransans, sans-serif; padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer;">بازگشت</button>
                        </div>
                    </div>
                </div>
            </main>
        </section>
    </div>
    </body>
    </html>
    <?php
}
?>
