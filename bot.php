<?php
include_once 'config.php';
check();

$robotState = $botState['botState']??"on";

GOTOSTART:
if ($userInfo['step'] == "banned" && $from_id != $admin && $userInfo['isAdmin'] != true) {
    sendMessage($mainValues['banned']);
    exit();
}
$checkSpam = checkSpam();
if(is_numeric($checkSpam)){
    $time = jdate("Y-m-d H:i:s", $checkSpam);
    sendMessage("اکانت شما به دلیل اسپم مسدود شده است\nزمان آزادسازی اکانت شما: \n$time");
    exit();
}
if(preg_match("/^haveJoined(.*)/",$data,$match)){
    if ($joniedState== "kicked" || $joniedState== "left"){
        alert($mainValues['not_joine_yet']);
        exit();
    }else{
        delMessage();
        $text = $match[1];
    }
}
if (($joniedState== "kicked" || $joniedState== "left") && $from_id != $admin){
    sendMessage(str_replace("CHANNEL-ID", $channelLock, $mainValues['join_channel_message']), json_encode(['inline_keyboard'=>[
        [['text'=>$buttonValues['join_channel'],'url'=>"https://t.me/" . str_replace("@", "", $botState['lockChannel'])]],
        [['text'=>$buttonValues['have_joined'],'callback_data'=>'haveJoined' . $text]],
        ]]),"HTML");
    exit;
}
if($robotState == "off" && $from_id != $admin){
    sendMessage($mainValues['bot_is_updating']);
    exit();
}
if(strstr($text, "/start ")){
    $inviter = str_replace("/start ", "", $text);
    if($inviter < 0) exit();
    if($uinfo->num_rows == 0 && $inviter != $from_id){
        $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
        $stmt->bind_param("i", $inviter);
        $stmt->execute();
        $inviterInfo = $stmt->get_result();
        $stmt->close();
        
        if($inviterInfo->num_rows > 0){
            $first_name = !empty($first_name)?$first_name:" ";
            $username = !empty($username)?$username:" ";
            if($uinfo->num_rows == 0){
                $sql = "INSERT INTO `users` (`userid`, `name`, `username`, `refcode`, `wallet`, `date`, `refered_by`)
                                    VALUES (?,?,?, 0,0,?,?)";
                $stmt = $connection->prepare($sql);
                $time = time();
                $stmt->bind_param("issii", $from_id, $first_name, $username, $time, $inviter);
                $stmt->execute();
                $stmt->close();
            }else{
                $refcode = time();
                $sql = "UPDATE `users` SET `refered_by` = ? WHERE `userid` = ?";
                $stmt = $connection->prepare($sql);
                $stmt->bind_param("si", $inviter, $from_id);
                $stmt->execute();
                $stmt->close();
            }
            $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid`=?");
            $stmt->bind_param("i", $from_id);
            $stmt->execute();
            $uinfo = $stmt->get_result();
            $userInfo = $uinfo->fetch_assoc();
            $stmt->close();
            
            setUser("referedBy" . $inviter);
            $userInfo['step'] = "referedBy" . $inviter;
            sendMessage($mainValues['invited_user_joined_message'],null,null, $inviter);
        }
    }
    
    $text = "/start";
}
if($userInfo['phone'] == null && $from_id != $admin && $userInfo['isAdmin'] != true && $botState['requirePhone'] == "on"){
    if(isset($update->message->contact)){
        $contact = $update->message->contact;
        $phone_number = $contact->phone_number;
        $phone_id = $contact->user_id;
        if($phone_id != $from_id){
            sendMessage($mainValues['please_select_from_below_buttons']);
            exit();
        }else{
            if(!preg_match('/^\+98(\d+)/',$phone_number) && !preg_match('/^98(\d+)/',$phone_number) && !preg_match('/^0098(\d+)/',$phone_number) && $botState['requireIranPhone'] == 'on'){
                sendMessage($mainValues['use_iranian_number_only']);
                exit();
            }
            setUser($phone_number, 'phone');
            
            sendMessage($mainValues['phone_confirmed'],$removeKeyboard);
            $text = "/start";
            
            $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid`=?");
            $stmt->bind_param("i", $from_id);
            $stmt->execute();
            $uinfo = $stmt->get_result();
            $userInfo = $uinfo->fetch_assoc();
            $stmt->close();
        }
    }else{
        sendMessage($mainValues['send_your_phone_number'], json_encode([
			'keyboard' => [[[
					'text' => $buttonValues['send_phone_number'],
					'request_contact' => true,
				]]],
			'resize_keyboard' => true
		]));
		exit();
    }
}
if(preg_match('/^\/([Ss]tart)/', $text) or $text == $buttonValues['back_to_main'] or $data == 'mainMenu') {
    setUser();
    setUser("", "temp"); 
    if(isset($data) and $data == "mainMenu"){
        $res = editText($message_id, $mainValues['start_message'], getMainKeys());
        if(!$res->ok){
            sendMessage($mainValues['start_message'], getMainKeys());
        }
    }else{
        if($from_id != $admin && empty($userInfo['first_start'])){
            setUser('sent','first_start');
            $keys = json_encode(['inline_keyboard'=>[
                [['text'=>$buttonValues['send_message_to_user'],'callback_data'=>'sendMessageToUser' . $from_id]]
            ]]);
    
            sendMessage(str_replace(["FULLNAME", "USERNAME", "USERID"], ["<a href='tg://user?id=$from_id'>$first_name</a>", $username, $from_id], $mainValues['new_member_joined'])
                ,$keys, "html",$admin);
        }
        sendMessage($mainValues['start_message'],getMainKeys());
    }
}
if(preg_match('/^sendMessageToUser(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    editText($message_id,'🔘|لطفا پیامت رو بفرست');
    setUser($data);
}
if(preg_match('/^sendMessageToUser(\d+)/',$userInfo['step'],$match) && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    sendMessage($text,null,null,$match[1]);
    sendMessage("پیامت به کاربر ارسال شد",$removeKeyboard);
    sendMessage($mainValues['reached_main_menu'],getAdminKeys());
    setUser();
}
if($data=='botReports' && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    editText($message_id, "آمار ربات در این لحظه",getBotReportKeys());
}
if($data=="adminsList" && $from_id == $admin){
    editText($message_id, "لیست ادمین ها",getAdminsKeys());
}
if(preg_match('/^delAdmin(\d+)/',$data,$match) && $from_id === $admin){
    $stmt = $connection->prepare("UPDATE `users` SET `isAdmin` = false WHERE `userid` = ?");
    $stmt->bind_param("i", $match[1]);
    $stmt->execute();
    $stmt->close();
    
    editText($message_id, "لیست ادمین ها",getAdminsKeys());

}
if($data=="addNewAdmin" && $from_id === $admin){
    delMessage();
    sendMessage("🧑‍💻| کسی که میخوای ادمین کنی رو آیدی عددیشو بفرست ببینم:",$cancelKey);
    setUser($data);
}
if($userInfo['step'] == "addNewAdmin" && $from_id === $admin && $text != $buttonValues['cancel']){
    if(is_numeric($text)){
        $stmt = $connection->prepare("UPDATE `users` SET `isAdmin` = true WHERE `userid` = ?");
        $stmt->bind_param("i", $text);
        $stmt->execute();
        $stmt->close();
        
        sendMessage("✅ | 🥳 خب کاربر الان ادمین شد تبریک میگم",$removeKeyboard);
        setUser();
        
        sendMessage("لیست ادمین ها",getAdminsKeys());
    }else{
        sendMessage($mainValues['send_only_number']);
    }
}
if(($data=="botSettings" or preg_match("/^changeBot(\w+)/",$data,$match)) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if($data!="botSettings"){
        if($match[1] == "cartToCartAutoAcceptType") $newValue = $botState[$match[1]] == "0"?"1":($botState[$match[1]] == "1"?"2":0);
        else $newValue = $botState[$match[1]]=="on"?"off":"on";
        setSettings($match[1], $newValue);
    }
    editText($message_id,$mainValues['change_bot_settings_message'],getBotSettingKeys());
}
if($data=="changeUpdateConfigLinkState" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $newValue = $botState['updateConnectionState']=="robot"?"site":"robot";
    setSettings('updateConnectionState', $newValue);
    editText($message_id,$mainValues['change_bot_settings_message'],getBotSettingKeys());
}
if(($data=="gateWays_Channels" or preg_match("/^changeGateWays(\w+)/",$data,$match)) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if($data!="gateWays_Channels"){
        $newValue = $botState[$match[1]]=="on"?"off":"on";
        setSettings($match[1], $newValue);
    }
    editText($message_id,$mainValues['change_bot_settings_message'],getGateWaysKeys());
}
if($data=="changeConfigRemarkType"){
    switch($botState['remark']){
        case "digits":
            $newValue = "manual";
            break;
        case "manual":
            $newValue = "idanddigits";
            break;
        default:
            $newValue = "digits";
            break;
    }
    setSettings('remark', $newValue);
    editText($message_id,$mainValues['change_bot_settings_message'],getBotSettingKeys());
}
if(preg_match('/^changePaymentKeys(\w+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    switch($match[1]){
        case "nextpay":
            $gate = "کد جدید درگاه نکست پی";
            break;
        case "nowpayment":
            $gate = "کد جدید درگاه nowPayment";
            break;
        case "zarinpal":
            $gate = "کد جدید درگاه زرین پال";
            break;
        case "bankAccount":
            $gate = "شماره حساب جدید";
            break;
        case "holderName":
            $gate = "اسم دارنده حساب";
            break;
        case "tronwallet":
            $gate = "آدرس والت ترون";
            break;
    }
    sendMessage("🔘|لطفا $gate را وارد کنید", $cancelKey);
    setUser($data);
}
if(preg_match('/^changePaymentKeys(\w+)/',$userInfo['step'],$match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){

    $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'PAYMENT_KEYS'");
    $stmt->execute();
    $paymentInfo = $stmt->get_result();
    $stmt->close();
    $paymentKeys = json_decode($paymentInfo->fetch_assoc()['value'],true)??array();
    $paymentKeys[$match[1]] = $text;
    $paymentKeys = json_encode($paymentKeys);
    
    if($paymentInfo->num_rows > 0) $stmt = $connection->prepare("UPDATE `setting` SET `value` = ? WHERE `type` = 'PAYMENT_KEYS'");
    else $stmt = $connection->prepare("INSERT INTO `setting` (`type`, `value`) VALUES ('PAYMENT_KEYS', ?)");
    $stmt->bind_param("s", $paymentKeys);
    $stmt->execute(); 
    $stmt->close();
    

    sendMessage($mainValues['saved_successfuly'],$removeKeyboard);
    sendMessage($mainValues['change_bot_settings_message'],getGateWaysKeys());
    setUser();
}
if(($data == "agentsList" || preg_match('/^nextAgentList(\d+)/',$data,$match)) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $keys = getAgentsList($match[1]??0);
    if($keys != null) editText($message_id,$mainValues['agents_list'], $keys);
    else alert("نماینده ای یافت نشد");
}
if(preg_match('/^agentDetails(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $userDetail = bot('getChat',['chat_id'=>$match[1]])->result;
    $userUserName = $userDetail->username;
    $fullName = $userDetail->first_name . " " . $userDetail->last_name;

    editText($message_id,str_replace("AGENT-NAME", $fullName, $mainValues['agent_details']), getAgentDetails($match[1]));
}
if(preg_match('/^removeAgent(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("UPDATE `users` SET `is_agent` = 0 WHERE `userid` = ?");
    $stmt->bind_param("i", $match[1]);
    $stmt->execute();
    $stmt->close();
    
    alert($mainValues['agent_deleted_successfuly']);
    $keys = getAgentsList();
    if($keys != null) editKeys($keys);
    else editKeys(json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_button'],'callback_data'=>"managePanel"]]]]));
}
if(preg_match('/^agentPercentDetails(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
    $stmt->bind_param('i',$match[1]);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $userName = $info['name'];
    editText($message_id, str_replace("AGENT-NAME", $userName, $mainValues['agent_discount_settings']), getAgentDiscounts($match[1]));
}
if(preg_match('/^addDiscount(Server|Plan)Agent(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
    $stmt->bind_param('i',$match[2]);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $userName = $info['name'];
    
    if($match[1] == "Plan"){
        $offset = 0;
        $limit = 20;
        
        $condition = array_values(array_keys(json_decode($info['discount_percent'],true)['plans']??array()));
        $condition = count($condition) > 0? "WHERE `id` NOT IN (" . implode(",", $condition) . ")":"";
        $stmt = $connection->prepare("SELECT * FROM `server_plans` $condition LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        $list = $stmt->get_result();
        $stmt->close();
        
        if($list->num_rows > 0){
            $keys = array();
            while($row = $list->fetch_assoc()){
                $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `id` = ?");
                $stmt->bind_param("i", $row['catid']);
                $stmt->execute();
                $catInfo = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                $keys[] = [['text'=>$row['title'] . " " . $catInfo['title'],'callback_data'=>"editAgentDiscountPlan" . $match[2] . "_" . $row['id']]];
            }
            
            if($list->num_rows >= $limit){
                $keys[] = [['text'=>"▶️",'callback_data'=>"nextAgentDiscountPlan" . $match[2] . "_" . ($offset + $limit)]];
            }
            $keys[] = [['text' => $buttonValues['back_button'], 'callback_data' => "agentPercentDetails" . $match[2]]];
            $keys = json_encode(['inline_keyboard'=>$keys]);
            
            editText($message_id,"لطفا سرور مورد نظر را برای افزودن تخفیف به نماینده $userName انتخاب کنید",$keys);
        }else alert("سروری باقی نمانده است");
    }else{
        $condition = array_values(array_keys(json_decode($info['discount_percent'],true)['servers']??array()));
        $condition = count($condition) > 0? "WHERE `id` NOT IN (" . implode(",", $condition) . ")":"";
        $stmt = $connection->prepare("SELECT * FROM `server_info` $condition");
        $stmt->execute();
        $list = $stmt->get_result();
        $stmt->close();
        
        if($list->num_rows > 0){
            $keys = array();
            while($row = $list->fetch_assoc()){
                $keys[] = [['text'=>$row['title'],'callback_data'=>"editAgentDiscountServer" . $match[2] . "_" . $row['id']]];
            }
            
            $keys[] = [['text' => $buttonValues['back_button'], 'callback_data' => "agentPercentDetails" . $match[2]]];
            $keys = json_encode(['inline_keyboard'=>$keys]);
            
            editText($message_id,"لطفا سرور مورد نظر را برای افزودن تخفیف به نماینده $userName انتخاب کنید",$keys);
        }else alert("سروری باقی نمانده است");
    }
}
if(preg_match('/^nextAgentDiscountPlan(?<agentId>\d+)_(?<offset>\d+)/',$data,$match) &&($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
    $stmt->bind_param('i',$match['agentId']);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $userName = $info['name'];
    
    $offset = $match['offset'];
    $limit = 20;
    
    $condition = array_values(array_keys(json_decode($info['discount_percent'],true)['plans']??array()));
    $condition = count($condition) > 0? "WHERE `id` NOT IN (" . implode(",", $condition) . ")":"";
    $stmt = $connection->prepare("SELECT * FROM `server_plans` $condition LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $list = $stmt->get_result();
    $stmt->close();
    
    if($list->num_rows > 0){
        $keys = array();
        while($row = $list->fetch_assoc()){
            $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `id` = ?");
            $stmt->bind_param("i", $row['catid']);
            $stmt->execute();
            $catInfo = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            $keys[] = [['text'=>$row['title'] . " " . $catInfo['title'],'callback_data'=>"editAgentDiscountPlan" . $match['agentId'] . "_" . $row['id']]];
        }
        
        if($list->num_rows >= $limit && $offset == 0){
            $keys[] = [['text'=>"▶️",'callback_data'=>"nextAgentDiscountPlan" . $match['agentId'] . "_" . ($offset + $limit)]];
        }
        elseif($list->num_rows >= $limit && $offset != 0){
            $keys[] = [
                ['text'=>"◀️️",'callback_data'=>"nextAgentDiscountPlan" . $match['agentId'] . "_" . ($offset - $limit)],
                ['text'=>"▶️",'callback_data'=>"nextAgentDiscountPlan" . $match['agentId'] . "_" . ($offset + $limit)]
                ];
        }
        elseif($offset != 0){
            $keys[] = [
                ['text'=>"◀️️",'callback_data'=>"nextAgentDiscountPlan" . $match['agentId'] . "_" . ($offset - $limit)]
                ];
        }
        $keys[] = [['text' => $buttonValues['back_button'], 'callback_data' => "agentPercentDetails" . $match['agentId']]];
        $keys = json_encode(['inline_keyboard'=>$keys]);
        
        editText($message_id,"لطفا سرور مورد نظر را برای افزودن تخفیف به نماینده $userName انتخاب کنید",$keys);
    }else alert("سروری باقی نمانده است");
}
if(preg_match('/^removePercentOfAgent(?<type>Server|Plan)(?<agentId>\d+)_(?<serverId>\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
    $stmt->bind_param('i',$match['agentId']);
    $stmt->execute();
    $info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $discounts = json_decode($info['discount_percent'],true);
    if($match['type'] == "Server") unset($discounts['servers'][$match['serverId']]);
    elseif($match['type'] == "Plan") unset($discounts['plans'][$match['serverId']]);
    
    $discounts = json_encode($discounts,488);
    $stmt = $connection->prepare("UPDATE `users` SET `discount_percent` = ? WHERE `userid` = ?");
    $stmt->bind_param("si", $discounts, $match['agentId']);
    $stmt->execute();
    $stmt->close();
    
    alert('با موفقیت حذف شد');
    editText($message_id, str_replace("AGENT-NAME", $userName, $mainValues['agent_discount_settings']), getAgentDiscounts($match['agentId']));
}
if(preg_match('/^editAgentDiscount(Server|Plan|Normal)(\d+)_(.*)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage($mainValues['send_agent_discount_percent'], $cancelKey);
    setUser($data);
}
if(preg_match('/^editAgentDiscount(Server|Plan|Normal)(\d+)_(.*)/',$userInfo['step'],$match) && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    if(is_numeric($text)){
        $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
        $stmt->bind_param('i',$match[2]);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $discountInfo = json_decode($info['discount_percent'],true);
        if($match[1] == "Server") $discountInfo['servers'][$match[3]] = $text;
        elseif($match[1] == "Plan") $discountInfo['plans'][$match[3]] = $text;
        elseif($match[1] == "Normal") $discountInfo['normal'] = $text;
        $text = json_encode($discountInfo);
        
        sendMessage($mainValues['saved_successfuly'],$removeKeyboard);
        
        $stmt = $connection->prepare("UPDATE `users` SET `discount_percent` = ? WHERE `userid` = ?");
        $stmt->bind_param("si", $text, $match[2]);
        $stmt->execute();
        $stmt->close();
        sendMessage(str_replace("AGENT-NAME", $userName, $mainValues['agent_discount_settings']), getAgentDiscounts($match[2]));
        setUser();
    }else sendMessage($mainValues['send_only_number']);
}
if(preg_match('/^edit(RewaredTime|cartToCartAutoAcceptTime)/', $data, $match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    if($match[1] == "RewaredTime") $txt = "🙃 | لطفا زمان تأخیر در ارسال گزارش رو به ساعت وارد کن\n\nنکته: هر n ساعت گزارش به ربات ارسال میشه! ";
    else $txt = "لطفا زمان مورد نظر را به دقیقه وارد کنید";
    
    sendMessage($txt,$cancelKey);
    setUser($data);
}
if($data=="userReports" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage("🙃 | لطفا آیدی عددی کاربر رو وارد کن",$cancelKey);
    setUser($data);
}
if($userInfo['step'] == "userReports" && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(is_numeric($text)){
        sendMessage($mainValues['please_wait_message'],$removeKeyboard);
        $keys = getUserInfoKeys($text);
        if($keys != null){
            sendMessage("اطلاعات کاربر <a href='tg://user?id=$text'>$fullName</a>",$keys,"html");
            setUser();
        }else sendMessage("کاربری با این آیدی یافت نشد");
    }else{
        sendMessage("😡|لطفا فقط عدد ارسال کن");
    }
}
if($data=="inviteSetting" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
    $stmt->execute();
    $inviteAmount = number_format($stmt->get_result()->fetch_assoc()['value']??0) . " تومان";
    $stmt->close();
    setUser();
    $keys = json_encode(['inline_keyboard'=>[
        [['text'=>"❗️بنر دعوت",'callback_data'=>"inviteBanner"]],
        [
            ['text'=>$inviteAmount,'callback_data'=>"editInviteAmount"],
            ['text'=>"مقدار پورسانت",'callback_data'=>"wizwizch"]
            ],
        [
            ['text'=>$buttonValues['back_button'],'callback_data'=>"botSettings"]
            ],
        ]]); 
    $res = editText($message_id,"✅ تنظیمات بازاریابی",$keys);
    if(!$res->ok){
        delMessage();
        sendMessage("✅ تنظیمات بازاریابی",$keys);
    }
} 
if($data=="inviteBanner" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_TEXT'");
    $stmt->execute();
    $inviteText = $stmt->get_result()->fetch_assoc()['value'];
    $inviteText = $inviteText != null?json_decode($inviteText,true):array('type'=>'text');
    $stmt->close();
    $keys = json_encode(['inline_keyboard'=>[
        [['text'=>"ویرایش",'callback_data'=>'editInviteBannerText']],
        [['text'=>$buttonValues['back_button'],'callback_data'=>'inviteSetting']]
        ]]);
    if($inviteText['type'] == "text"){
        editText($message_id,"بنر فعلی: \n" . $inviteText['text'],$keys);
    }else{
        delMessage();
        $res = sendPhoto($inviteText['file_id'], $inviteText['caption'], $keys,null);
        if(!$res->ok){
            sendMessage("تصویر فعلی یافت نشد، لطفا اقدام به ویرایش بنر کنید",$keys);
        }
    }
    setUser();
}
if($data=="editInviteBannerText" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage("🤖 | لطفا بنر جدید را بفرستید از متن  LINK برای نمایش لینک دعوت استفاده کنید)",$cancelKey);
    setUser($data);
}
if($userInfo['step']=="editInviteBannerText" && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    $data = array();
    if(isset($update->message->photo)){
        $data['type'] = 'photo';
        $data['caption'] = $caption;
        $data['file_id'] = $fileid;
    }
    elseif(isset($update->message->text)){
        $data['type'] = 'text';
        $data['text'] = $text;
    }else{
        sendMessage("🥺 | بنر ارسال شده پشتیبانی نمی شود");
        exit();
    }
    
    $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_TEXT'");
    $stmt->execute();
    $checkExist = $stmt->get_result();
    $stmt->close();
    $data = json_encode($data);
    if($checkExist->num_rows > 0){
        $stmt = $connection->prepare("UPDATE `setting` SET `value` = ? WHERE `type` = 'INVITE_BANNER_TEXT'");
        $stmt->bind_param("s", $data);
        $stmt->execute();
        $checkExist = $stmt->get_result();
        $stmt->close();
    }else{
        $stmt = $connection->prepare("INSERT INTO `setting` (`value`, `type`) VALUES (?, 'INVITE_BANNER_TEXT')");
        $stmt->bind_param("s", $data);
        $stmt->execute();
        $checkExist = $stmt->get_result();
        $stmt->close();
    }
    
    sendMessage($mainValues['saved_successfuly'],$removeKeyboard);
    $keys = json_encode(['inline_keyboard'=>[
        [['text'=>"ویرایش",'callback_data'=>'editInviteBannerText']],
        [['text'=>$buttonValues['back_button'],'callback_data'=>'inviteSetting']]
        ]]);
    if(isset($update->message->text)){
        sendMessage("بنر فعلی: \n" . $text,$keys);
    }else{
        sendPhoto($fileid, $caption, $keys);
    }
    setUser();
}
if($data=="editInviteAmount" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage("لطفا مبلغ پورسانت رو به تومان وارد کن",$cancelKey);
    setUser($data);
} 
if($userInfo['step'] == "editInviteAmount" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(is_numeric($text)){
        $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
        $stmt->execute();
        $checkExist = $stmt->get_result();
        $stmt->close();
        
        if($checkExist->num_rows > 0){
            $stmt = $connection->prepare("UPDATE `setting` SET `value` = ? WHERE `type` = 'INVITE_BANNER_AMOUNT'");
            $stmt->bind_param("s", $text);
            $stmt->execute();
            $checkExist = $stmt->get_result();
            $stmt->close();
        }else{
            $stmt = $connection->prepare("INSERT INTO `setting` (`value`, `type`) VALUES (?, 'INVITE_BANNER_AMOUNT')");
            $stmt->bind_param("s", $text);
            $stmt->execute();
            $checkExist = $stmt->get_result();
            $stmt->close();
        }
        sendMessage($mainValues['saved_successfuly'],$removeKeyboard);
        
        $keys = json_encode(['inline_keyboard'=>[
            [['text'=>"❗️بنر دعوت",'callback_data'=>"inviteBanner"]],
            [
                ['text'=>number_format($text) . " تومان",'callback_data'=>"editInviteAmount"],
                ['text'=>"مقدار پورسانت",'callback_data'=>"wizwizch"]
                ], 
            [
                ['text'=>$buttonValues['back_button'],'callback_data'=>"botSettings"]
                ],
            ]]); 
        sendMessage("✅ تنظیمات بازاریابی",$keys);
        setUser();
    }else sendMessage($mainValues['send_only_number']);
}
if(preg_match('/^edit(RewaredTime|cartToCartAutoAcceptTime)/', $userInfo['step'], $match) && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    if(!is_numeric($text)){
        sendMessage("لطفا عدد بفرستید");
        exit();
    }
    elseif($text <0 ){
        sendMessage("مقدار وارد شده معتبر نیست");
        exit();
    }
    
    setSettings(lcfirst($match[1]), $text);
    sendMessage($mainValues['change_bot_settings_message'],getBotSettingKeys());
    setUser();
    exit();
}
if($data=="inviteFriends"){
    $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_TEXT'");
    $stmt->execute();
    $inviteText = $stmt->get_result()->fetch_assoc()['value'];
    if($inviteText != null){
        delMessage();
        $inviteText = json_decode($inviteText,true);
    
        $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
        $stmt->execute();
        $inviteAmount = number_format($stmt->get_result()->fetch_assoc()['value']??0) . " تومان";
        $stmt->close();
        
        $getBotInfo = json_decode(file_get_contents("http://api.telegram.org/bot" . $botToken . "/getMe"),true);
        $botId = $getBotInfo['result']['username'];
        
        $link = "t.me/$botId?start=" . $from_id;
        if($inviteText['type'] == "text"){
            $txt = str_replace('LINK',"<code>$link</code>",$inviteText['text']);
            $res = sendMessage($txt,null,"HTML");
        } 
        else{
            $txt = str_replace('LINK',"$link",$inviteText['caption']);
            $res = sendPhoto($inviteText['file_id'],$txt,null,"HTML");
        }
        $msgId = $res->result->message_id;
        sendMessage("با لینک بالا دوستاتو به ربات دعوت کن و با هر خرید $inviteAmount بدست بیار",json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),null,null,$msgId);
    }
    else alert("این قسمت غیر فعال است");
}
if($data=="myInfo"){
    $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `userid` = ?");
    $stmt->bind_param("i", $from_id);
    $stmt->execute();
    $totalBuys = $stmt->get_result()->num_rows;
    $stmt->close();
    
    $myWallet = number_format($userInfo['wallet']) . " تومان";
    
    $keys = json_encode(['inline_keyboard'=>[
        [
            ['text'=>"شارژ کیف پول 💰",'callback_data'=>"increaseMyWallet"],
            ['text'=>"انتقال موجودی",'callback_data'=>"transferMyWallet"]
        ],
        [
            ['text'=>$buttonValues['back_button'],'callback_data'=>"mainMenu"]
            ]
        ]]);
    editText($message_id, "
💞 اطلاعات حساب شما:
    
🔰 شناسه کاربری: <code> $from_id </code>
🍄 یوزرنیم: <code> @$username </code>
👤 اسم:  <code> $first_name </code>
💰 موجودی: <code> $myWallet </code>

☑️ کل سرویس ها : <code> $totalBuys </code> عدد
⁮⁮ ⁮⁮ ⁮⁮ ⁮⁮
",
            $keys,"html");
}
if($data=="transferMyWallet"){
    if($userInfo['wallet'] > 0 ){
        delMessage();
        sendMessage("لطفا آیدی عددی کاربر مورد نظر رو وارد کن",$cancelKey);
        setUser($data);
    }else alert("موجودی حساب شما کم است");
}
if($userInfo['step'] =="transferMyWallet" && $text != $buttonValues['cancel']){
    if(is_numeric($text)){
        if($text != $from_id){
            $stmt= $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
            $stmt->bind_param("i", $text);
            $stmt->execute();
            $checkExist = $stmt->get_result();
            $stmt->close();
            
            if($checkExist->num_rows > 0){
                setUser("tranfserUserAmount" . $text);
                sendMessage("لطفا مبلغ مورد نظر رو وارد کن");
            }else sendMessage("کاربری با این آیدی یافت نشد");
        }else sendMessage("میخای به خودت انتقال بدی ؟؟");
    }else sendMessage($mainValues['send_only_number']);
}
if(preg_match('/^tranfserUserAmount(\d+)/',$userInfo['step'],$match) && $text != $buttonValues['cancel']){
    if(is_numeric($text)){
        if($text > 0){
            if($userInfo['wallet'] >= $text){
                $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
                $stmt->bind_param("ii", $text, $match[1]);
                $stmt->execute();
                $stmt->close();
                
                $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` - ? WHERE `userid` = ?");
                $stmt->bind_param("ii", $text, $from_id);
                $stmt->execute();
                $stmt->close();
                
                sendMessage("✅|مبلغ " . number_format($text) . " تومان به کیف پول شما توسط کاربر $from_id انتقال یافت",null,null,$match[1]);
                setUser();
                sendMessage("✅|مبلغ " . number_format($text) . " تومان به کیف پول کاربر مورد نظر شما انتقال یافت",$removeKeyboard);
                sendMessage("لطفا یکی از کلید های زیر را انتخاب کنید",getMainKeys());
            }else sendMessage("موجودی حساب شما کم است");
        }else sendMessage("لطفا عددی بزرگتر از صفر وارد کنید");
    }else sendMessage($mainValues['send_only_number']);
}
if($data=="increaseMyWallet"){
    delMessage();
    sendMessage("🙂 عزیزم مقدار شارژ مورد نظر خود را به تومان وارد کن (بیشتر از 5000 تومان)",$cancelKey);
    setUser($data);
}
if($userInfo['step'] == "increaseMyWallet" && $text != $buttonValues['cancel']){
    if(!is_numeric($text)){
        sendMessage($mainValues['send_only_number']);
        exit();
    }
    elseif($text < 5000){
        sendMessage("لطفا مقداری بیشتر از 5000 وارد کن");
        exit();
    }
    sendMessage("🪄 لطفا صبور باشید ...",$removeKeyboard);
    $hash_id = RandomString();
    $stmt = $connection->prepare("DELETE FROM `pays` WHERE `user_id` = ? AND `type` = 'INCREASE_WALLET' AND `state` = 'pending'");
    $stmt->bind_param("i", $from_id);
    $stmt->execute();
    $stmt->close();
    
    $time = time();
    $stmt = $connection->prepare("INSERT INTO `pays` (`hash_id`, `user_id`, `type`, `plan_id`, `volume`, `day`, `price`, `request_date`, `state`)
                                VALUES (?, ?, 'INCREASE_WALLET', '0', '0', '0', ?, ?, 'pending')");
    $stmt->bind_param("siii", $hash_id, $from_id, $text, $time);
    $stmt->execute();
    $stmt->close();
    
    
    $keyboard = array();
    if($botState['cartToCartState'] == "on") $keyboard[] = [['text' => $buttonValues['cart_to_cart'],  'callback_data' => "increaseWalletWithCartToCart" . $hash_id]];
    if($botState['nowPaymentWallet'] == "on") $keyboard[] = [['text' => $buttonValues['now_payment_gateway'],  'url' => $botUrl . "pay/?nowpayment&hash_id=" . $hash_id]];
    if($botState['zarinpal'] == "on") $keyboard[] = [['text' => $buttonValues['zarinpal_gateway'],  'url' => $botUrl . "pay/?zarinpal&hash_id=" . $hash_id]];
    if($botState['nextpay'] == "on") $keyboard[] = [['text' => $buttonValues['nextpay_gateway'],  'url' => $botUrl . "pay/?nextpay&hash_id=" . $hash_id]];
    if($botState['weSwapState'] == "on") $keyboard[] = [['text' => $buttonValues['weswap_gateway'],  'callback_data' => "payWithWeSwap" . $hash_id]];
    if($botState['tronWallet'] == "on") $keyboard[] = [['text' => $buttonValues['tron_gateway'],  'callback_data' => "payWithTronWallet" . $hash_id]];

    $keyboard[] = [['text'=>$buttonValues['cancel'], 'callback_data'=> "mainMenu"]];

    
	$keys = json_encode(['inline_keyboard'=>$keyboard]);
    sendMessage("اطلاعات شارژ:\nمبلغ ". number_format($text) . " تومان\n\nلطفا روش پرداخت را انتخاب کنید",$keys);
    setUser();
}
if(preg_match('/increaseWalletWithCartToCart(?<hashId>.*)/',$data, $match)) {
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param('s', $match['hashId']);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();
    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    
    delMessage();  
    setUser($data);

    sendMessage(str_replace(["ACCOUNT-NUMBER", "HOLDER-NAME"],[$paymentKeys['bankAccount'],$paymentKeys['holderName']], $mainValues['increase_wallet_cart_to_cart']),$cancelKey, "HTML");
    exit;
}
if(preg_match('/increaseWalletWithCartToCart(.*)/',$userInfo['step'], $match) and $text != $buttonValues['cancel']){
    if(isset($update->message->photo)){
        setUser();
        $uid = $userInfo['userid'];
        $name = $userInfo['name'];
        $username = $userInfo['username'];
    
        $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
        $stmt->bind_param("s", $match[1]);
        $stmt->execute();
        $payInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $price = number_format($payInfo['price']);

    

        sendMessage($mainValues['order_increase_sent'],$removeKeyboard);
        sendMessage($mainValues['reached_main_menu'],getMainKeys());
        $msg = str_replace(['PRICE', 'USERNAME', 'NAME', 'USER-ID'],[$price, $username, $name, $from_id], $mainValues['increase_wallet_request_message']);
        
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $buttonValues['approve'], 'callback_data' => "approvePayment{$match[1]}"],
                    ['text' => $buttonValues['decline'], 'callback_data' => "decPayment{$match[1]}"]
                ]
            ]
        ]);
        $res = sendPhoto($fileid, $msg,$keyboard, "HTML", $admin);
        $msgId = $res->result->message_id;
        
        $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'have_sent', `message_id` = ?, `chat_id` = ? WHERE `hash_id` = ?");
        $stmt->bind_param("iis", $msgId, $admin, $match[1]);
        $stmt->execute();
        $stmt->close();
    }else{
        sendMessage($mainValues['please_send_only_image']);
    }
}
if(preg_match('/^approvePayment(.*)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $price = $payInfo['price'];
    $userId = $payInfo['user_id'];
    
    if($payInfo['state'] == "approved") exit();
    
    $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'approved' WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $stmt->close();
    

    $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
    $stmt->bind_param("ii", $price, $userId);
    $stmt->execute();
    $stmt->close();

    sendMessage("افزایش حساب شما با موفقیت تأیید شد\n✅ مبلغ " . number_format($price). " تومان به حساب شما اضافه شد",null,null,$userId);
    
    unset($markup[count($markup)-1]);
    $markup[] = [['text' => '✅', 'callback_data' => "dontsendanymore"]];
    $keys = json_encode(['inline_keyboard'=>array_values($markup)],488);

    editKeys($keys);
}
if(preg_match('/^decPayment(.*)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    unset($markup[count($markup)-1]);
    $markup[] = [['text' => '❌', 'callback_data' => "dontsendanymore"]];
    $keys = json_encode(['inline_keyboard'=>array_values($markup)],488);
    file_put_contents("temp" . $from_id . ".txt", $keys);
    sendMessage("لطفا دلیل عدم تأیید افزایش موجودی را وارد کنید",$cancelKey);
    setUser("decPayment" . $message_id . "_" . $match[1]);
}
if(preg_match('/^decPayment(\d+)_(.*)/',$userInfo['step'],$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[2]);
    $stmt->execute();
    $payInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $price = $payInfo['price'];
    $userId = $payInfo['user_id'];
    
    $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'declined' WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[2]);
    $stmt->execute();
    $stmt->close();
    
    sendMessage("💔 افزایش موجودی شما به مبلغ "  . number_format($price) . " به دلیل زیر رد شد\n\n$text",null,null,$userId);


    editKeys(file_get_contents("temp" . $from_id . ".txt"), $match[1]);
    setUser();
    sendMessage('پیامت رو براش ارسال کردم ... 🤝',$removeKeyboard);
    unlink("temp" . $from_id . ".txt");
}
if($data=="increaseUserWallet" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage($mainValues['send_user_id'],$cancelKey);
    setUser($data);
}
if($userInfo['step'] == "increaseUserWallet" && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    if(is_numeric($text)){
        $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
        $stmt->bind_param("i", $text);
        $stmt->execute();
        $userCount = $stmt->get_result()->num_rows;
        $stmt->close();
        if($userCount > 0){
            setUser("increaseWalletUser" . $text);
            sendMessage($mainValues['enter_increase_amount']);
        }
        else{
            setUser();
            sendMessage($mainValues['user_not_found'], $removeKeyboard);
            sendMessage($mainValues['reached_main_menu'],getMainKeys());
        }
    }else{
        sendMessage($mainValues['send_only_number']);
    }
}
if(preg_match('/^increaseWalletUser(\d+)/',$userInfo['step'], $match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(is_numeric($text)){
        $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
        $stmt->bind_param("ii", $text, $match[1]);
        $stmt->execute();
        $stmt->close();
    
        sendMessage("✅ مبلغ " . number_format($text). " تومان به حساب شما اضافه شد",null,null,$match[1]);
        sendMessage("✅ مبلغ " . number_format($text) . " تومان به کیف پول کاربر مورد نظر اضافه شد",$removeKeyboard);
        sendMessage($mainValues['reached_main_menu'],getMainKeys());
        setUser();
    }else{
        sendMessage($mainValues['send_only_number']);
    }
}
if($data=="decreaseUserWallet" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage($mainValues['send_user_id'],$cancelKey);
    setUser($data);
}
if($userInfo['step'] == "decreaseUserWallet" && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    if(is_numeric($text)){
        $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
        $stmt->bind_param("i", $text);
        $stmt->execute();
        $userCount = $stmt->get_result()->num_rows;
        $stmt->close();
        if($userCount > 0){
            setUser("decreaseWalletUser" . $text);
            sendMessage($mainValues['enter_decrease_amount']);
        }
        else{
            setUser();
            sendMessage($mainValues['user_not_found'], $removeKeyboard);
            sendMessage($mainValues['reached_main_menu'],getMainKeys());
        }
    }else{
        sendMessage($mainValues['send_only_number']);
    }
}
if(preg_match('/^decreaseWalletUser(\d+)/',$userInfo['step'], $match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(is_numeric($text)){
        $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` - ? WHERE `userid` = ?");
        $stmt->bind_param("ii", $text, $match[1]);
        $stmt->execute();
        $stmt->close();
    
        sendMessage(str_replace("AMOUNT", number_format($text), $mainValues['amount_decreased_from_your_wallet']),null,null,$match[1]);
        sendMessage(str_replace("AMOUNT", number_format($text), $mainValues['amount_decreased_from_user_wallet']),$removeKeyboard);
        sendMessage($mainValues['reached_main_menu'],getMainKeys());
        setUser();
    }else{
        sendMessage($mainValues['send_only_number']);
    }
}
if($data=="editRewardChannel" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage("🤗|لطفا ربات رو در کانال ادمین کن و آیدی کانال رو بفرست",$cancelKey);
    setUser($data);
}
if($userInfo['step'] == "editRewardChannel" && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    $botId = json_decode(file_get_contents("https://api.telegram.org/bot$botToken/getme"))->result->id;
    $result = json_decode(file_get_contents("https://api.telegram.org/bot$botToken/getChatMember?chat_id=$text&user_id=$botId"));
    if($result->ok){
        if($result->result->status == "administrator"){
            setSettings('rewardChannel', $text);
            sendMessage($mainValues['change_bot_settings_message'],getGateWaysKeys());
            setUser();
            exit();
        }
    }
    sendMessage("😡|ای بابا ،ربات هنوز تو کانال عضو نشده، اول ربات رو تو کانال ادمین کن و آیدیش رو بفرست");
}
if($data=="editLockChannel" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage("🤗|لطفا ربات رو در کانال ادمین کن و آیدی کانال رو بفرست",$cancelKey);
    setUser($data);
}
if($userInfo['step'] == "editLockChannel" && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    $botId = json_decode(file_get_contents("https://api.telegram.org/bot$botToken/getme"))->result->id;
    $result = json_decode(file_get_contents("https://api.telegram.org/bot$botToken/getChatMember?chat_id=$text&user_id=$botId"));
    if($result->ok){
        if($result->result->status == "administrator"){
            setSettings("lockChannel", $text);
            sendMessage($mainValues['change_bot_settings_message'],getGateWaysKeys());
            setUser();
            exit();
        }
    }
    sendMessage($mainValues['the_bot_in_not_admin']);
}
if(($data == "agentOneBuy" || $data=='buySubscription' || $data == "agentMuchBuy") && ($botState['sellState']=="on" || ($from_id == $admin || $userInfo['isAdmin'] == true))){
    if($botState['cartToCartState'] == "off" && $botState['walletState'] == "off"){
        alert($mainValues['selling_is_off']);
        exit();
    }
    if($data=="buySubscription") $buyType = "none";
    elseif($data=="agentOneBuy") $buyType = "one";
    elseif($data== "agentMuchBuy") $buyType = "much";
    
    $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `active`=1 and `state` = 1 and `ucount` > 0 ORDER BY `id` ASC");
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    if($respd->num_rows==0){
        alert($mainValues['no_server_available']);
        exit;
    }
    $keyboard = [];
    while($cat = $respd->fetch_assoc()){
        $id = $cat['id'];
        $name = $cat['title'];
        $flag = $cat['flag'];
        $keyboard[] = ['text' => "$flag $name", 'callback_data' => "selectServer{$id}_{$buyType}"];
    }
    $keyboard = array_chunk($keyboard,1);
    $keyboard[] = [['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]];
    editText($message_id, $mainValues['buy_sub_select_location'], json_encode(['inline_keyboard'=>$keyboard]));
}
if($data=='createMultipleAccounts' && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `active`=1 and `ucount` > 0 ORDER BY `id` ASC");
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    if($respd->num_rows==0){
        sendMessage($mainValues['no_server_available']);
        exit;
    }
    $keyboard = [];
    while($cat = $respd->fetch_assoc()){
        $id = $cat['id'];
        $name = $cat['title'];
        $flag = $cat['flag'];
        $keyboard[] = ['text' => "$flag $name", 'callback_data' => "createAccServer$id"];
    }
    $keyboard[] = ['text'=>$buttonValues['back_to_main'],'callback_data'=>"managePanel"];
    $keyboard = array_chunk($keyboard,1);
    editText($message_id, $mainValues['buy_sub_select_location'], json_encode(['inline_keyboard'=>$keyboard]));
    

}
if(preg_match('/createAccServer(\d+)/',$data, $match) && ($from_id == $admin || $userInfo['isAdmin'] == true) ) {
    $sid = $match[1];
        
    $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `parent`=0 order by `id` asc");
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    if($respd->num_rows == 0){
        alert("هیچ دسته بندی برای این سرور وجود ندارد");
    }else{
        
        $keyboard = [];
        while ($file = $respd->fetch_assoc()){
            $id = $file['id'];
            $name = $file['title'];
            $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `server_id`=? and `catid`=? and `active`=1");
            $stmt->bind_param("ii", $sid, $id);
            $stmt->execute();
            $rowcount = $stmt->get_result()->num_rows; 
            $stmt->close();
            if($rowcount>0) $keyboard[] = ['text' => "$name", 'callback_data' => "createAccCategory{$id}_{$sid}"];
        }
        if(empty($keyboard)){
            alert("هیچ دسته بندی برای این سرور وجود ندارد");exit;
        }
        alert("♻️ | دریافت دسته بندی ...");
        $keyboard[] = ['text' => $buttonValues['back_to_main'], 'callback_data' => "createMultipleAccounts"];
        $keyboard = array_chunk($keyboard,1);
        editText($message_id, "2️⃣ مرحله دو:

دسته بندی مورد نظرت رو انتخاب کن 🤭", json_encode(['inline_keyboard'=>$keyboard]));
    }

}
if(preg_match('/createAccCategory(\d+)_(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)) {
    $call_id = $match[1];
    $sid = $match[2];
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `server_id`=? and `catid`=? and `active`=1 order by `id` asc");
    $stmt->bind_param("ii", $sid, $call_id);
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    if($respd->num_rows==0){
        alert("💡پلنی در این دسته بندی وجود ندارد ");
    }else{
        alert("📍در حال دریافت لیست پلن ها");
        $keyboard = [];
        while($file = $respd->fetch_assoc()){
            $id = $file['id'];
            $name = $file['title'];
            $keyboard[] = ['text' => "$name", 'callback_data' => "createAccPlan{$id}"];
        }
        $keyboard[] = ['text' => $buttonValues['back_to_main'], 'callback_data' => "createAccServer$sid"];
        $keyboard = array_chunk($keyboard,1);
        editText($message_id, "3️⃣ مرحله سه:

یکی از پلن هارو انتخاب کن و برو برای پرداختش 🤲 🕋", json_encode(['inline_keyboard'=>$keyboard]));
    }

}
if(preg_match('/^createAccPlan(\d+)/',$data,$match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage("❗️لطفا مدت زمان اکانت را به ( روز ) وارد کن:",$cancelKey);
    setUser('createAccDate' . $match[1]);
}
if(preg_match('/^createAccDate(\d+)/',$userInfo['step'],$match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(is_numeric($text)){
        if($text >0){
            sendMessage("❕حجم اکانت ها رو به گیگابایت ( GB ) وارد کن:");
            setUser('createAccVolume' . $match[1] . "_" . $text);
        }else{
            sendMessage("عدد باید بیشتر از 0 باشه");
        }
    }else{
        sendMessage('😡 | مگه نمیگم فقط عدد بفرس نمیفهمی؟ یا خودتو زدی به نفهمی؟');
    }
}
if(preg_match('/^createAccVolume(\d+)_(\d+)/',$userInfo['step'],$match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(!is_numeric($text)){
        sendMessage($mainValues['send_only_number']);
        exit();
    }elseif($text <=0){
        sendMessage("مقداری بزرگتر از 0 وارد کن");
        exit();
    }
    sendMessage($mainValues['enter_account_amount']);
    setUser("createAccAmount" . $match[1] . "_" . $match[2] . "_" . $text);
}
if(preg_match('/^createAccAmount(\d+)_(\d+)_(\d+)/',$userInfo['step'], $match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(!is_numeric($text)){
        sendMessage($mainValues['send_only_number']);
        exit();
    }elseif($text <=0){
        sendMessage("مقداری بزرگتر از 0 وارد کن");
        exit();
    }
    $uid = $from_id;
    $fid = $match[1];
    $acctxt = '';
    
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $days = $match[2];
    $date = time();
    $expire_microdate = floor(microtime(true) * 1000) + (864000 * $days * 100);
    $expire_date = $date + (86400 * $days);
    $type = $file_detail['type'];
    $volume = $match[3];
    $protocol = $file_detail['protocol'];
    $price = $file_detail['price'];
    $rahgozar = $file_detail['rahgozar'];
    $customPath = $file_detail['custom_path'];
    $customPort = $file_detail['custom_port'];
    $customSni = $file_detail['custom_sni'];
    
    
    
    $server_id = $file_detail['server_id'];
    $netType = $file_detail['type'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];
    $limitip = $file_detail['limitip'];


    if($acount == 0 and $inbound_id != 0){
        alert($mainValues['out_of_connection_capacity']);
        exit;
    }
    if($inbound_id == 0) {
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($server_info['ucount'] <= 0) {
            alert($mainValues['out_of_server_capacity']);
            exit;
        }
    }else{
        if($acount < $text) {
            sendMessage(str_replace("AMOUNT", $acount, $mainValues['can_create_specific_account']));
            exit();
        }
    }

    $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverInfo = $stmt->get_result()->fetch_assoc();
    $srv_remark = $serverInfo['remark'];
    $stmt->close();
    $savedinfo = file_get_contents('settings/temp.txt');
    $savedinfo = explode('-',$savedinfo);
    $port = $savedinfo[0];
    $last_num = $savedinfo[1];
    include 'phpqrcode/qrlib.php';
    $ecc = 'L';
    $pixel_Size = 11;
    $frame_Size = 0;
    
    $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverConfig = $stmt->get_result()->fetch_assoc();
    $serverType = $serverConfig['type'];
    $portType = $serverConfig['port_type'];
    $panelUrl = $serverConfig['panel_url'];
    $stmt->close();


	$stmt = $connection->prepare("INSERT INTO `orders_list` 
	    (`userid`, `token`, `transid`, `fileid`, `server_id`, `inbound_id`, `remark`, `uuid`, `protocol`, `expire_date`, `link`, `amount`, `status`, `date`, `notif`, `rahgozar`)
	    VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?,1, ?, 0, ?);");
    define('IMAGE_WIDTH',540);
    define('IMAGE_HEIGHT',540);
    for($i = 1; $i<= $text; $i++){
        $uniqid = generateRandomString(42,$protocol); 
        if($portType == "auto"){
            $port++;
        }else{
            $port = rand(1111,65000);
        }
        $last_num++;
        
        if($botState['remark'] == "digits"){
            $rnd = rand(10000,99999);
            $remark = "{$srv_remark}-{$rnd}";
        }else{
            $rnd = rand(1111,99999);
            $remark = "{$srv_remark}-{$from_id}-{$rnd}";
        }
    
        if($inbound_id == 0){                    
            if($serverType == "marzban"){
                $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                if(!$response->success){
                    if(isset($response->msg) && $response->msg == "User already exists"){
                        $remark .= rand(1111,99999);
                        $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                    }
                }
            }
            else{
                $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid); 
                
                if(!$response->success){
                    if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();
                    elseif(isset($response->msg) && strstr($response->msg, "Port already exists")) $port = rand(1111,65000);
    
                    $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid); 
                }
            }
        }else {
            $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid); 
            if(!$response->success){
                if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();
                
                $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid); 
            }
        }
        
        if(is_null($response)){
            sendMessage('❌ | 🥺 گلم ، اتصال به سرور برقرار نیست لطفا مدیر رو در جریان بزار ...');
            break;
        }
	if(isset($response->msg) && $response->msg == "inbound not Found"){
            sendMessage("❌ | 🥺 سطر (inbound) با آیدی $inbound_id تو این سرور وجود نداره ، مدیر رو در جریان بزار ...");
            break;
    	}
    	if(!$response->success){
            sendMessage('❌ | 😮 وای خطا داد لطفا سریع به مدیر بگو ...');
            sendMessage("خطای سرور {$serverInfo['title']}:\n\n" . (isset($response->msg)?$response->msg:"Null response"), null, null, $admin);
            break;
        }
    
        if($serverType == "marzban"){
            $uniqid = $token = str_replace("/sub/", "", $response->obj->subscription_url);
            $subLink = $botState['subLinkState'] == "on"?$panelUrl . $response->obj->subscription_url:"";
            $vraylink = $response->obj->links;
            $vray_link = json_encode($vraylink);
        }
        else{
            $token = RandomString(30);
            $vraylink = getConnectionLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id, $rahgozar, $customPath, $customPort, $customSni);
            $subLink = $botState['subLinkState']=="on"?$botUrl . "settings/subLink.php?token=" . $token:"";
            $vray_link = json_encode($vraylink);
        }
        foreach($vraylink as $link){
            $acc_text = "
    
        🔮 $remark \n " . ($botState['configLinkState'] != "off" && $serverType != "marzban"?"<code>$link</code>":"");
            if($botState['subLinkState'] == "on") $acc_text .= 
            " \n🌐 subscription : <code>$subLink</code>";
        
            $file = RandomString() .".png";
            
            QRcode::png($link, $file, $ecc, $pixel_Size, $frame_Size);
        	addBorderImage($file);
        	
        	
        	$backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
            $qrImage = imagecreatefrompng($file);
            
            $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
            imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
            imagepng($backgroundImage, $file);
            imagedestroy($backgroundImage);
            imagedestroy($qrImage);


        	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
            unlink($file);
        }
        $stmt->bind_param("ssiiisssisiii", $uid, $token, $fid, $server_id, $inbound_id, $remark, $uniqid, $protocol, $expire_date, $vray_link, $price, $date, $rahgozar);
        $stmt->execute();
    }
    $stmt->close();
    if($portType == "auto"){
        file_put_contents('settings/temp.txt',$port.'-'.$last_num);
    }
    if($inbound_id == 0) {
        $stmt = $connection->prepare("UPDATE `server_info` SET `ucount` = `ucount` - 1 WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $stmt->close();
    }else{
        $stmt = $connection->prepare("UPDATE `server_plans` SET `acount` = `acount` - ? WHERE id=?");
        $stmt->bind_param("ii", $text, $fid);
        $stmt->execute();
        $stmt->close();
    }
    sendMessage("☑️|❤️ اکانت های جدید با موفقیت ساخته شد",getMainKeys());
    setUser();
}
if(preg_match('/payWithTronWallet(.*)/',$data,$match)) {
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();

    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    $payInfo = $payInfo->fetch_assoc();

    $fid = $payInfo['plan_id'];
    $type = $payInfo['type'];
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $server_id = $file_detail['server_id'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];

    if($type != "INCREASE_WALLET" && $type != "RENEW_ACCOUNT"){
        if($acount <= 0 and $inbound_id != 0){
            alert($mainValues['out_of_connection_capacity']);
            exit;
        }
        if($inbound_id == 0) {
            $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
            $stmt->bind_param("i", $server_id);
            $stmt->execute();
            $server_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
    
            if($server_info['ucount'] <= 0) {
                alert($mainValues['out_of_server_capacity']);
                exit; 
            }
        }else{
            if($acount <= 0){
                alert($mainValues['out_of_server_capacity']);
                exit();
            }
        }
    }
    
    if($type == "RENEW_ACCOUNT"){
        $oid = $payInfo['plan_id'];
        
        $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `id` = ?");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        $order = $stmt->get_result();
        $stmt->close();
        if($order->num_rows == 0){
            delMessage();
            sendMessage($mainValues['config_not_found'], getMainKeys());
            exit();
        }

    }
    
    delMessage();
    
    $price = $payInfo['price'];
    $priceInTrx = round($price / $botState['TRXRate'],2);
    
    $stmt = $connection->prepare("UPDATE `pays` SET `tron_price` = ? WHERE `hash_id` = ?");
    $stmt->bind_param("ds", $priceInTrx, $match[1]);
    $stmt->execute();
    $stmt->close();
    
    sendMessage(str_replace(["AMOUNT", "TRON-WALLET"], [$priceInTrx, $paymentKeys['tronwallet']], $mainValues['pay_with_tron_wallet']), $cancelKey, "html");
    setUser($data);
}
if(preg_match('/^payWithTronWallet(.*)/',$userInfo['step'], $match) && $text != $buttonValues['cancel']){
    if(!preg_match('/^[0-9a-f]{64}$/i',$text)){
        sendMessage($mainValues['incorrect_tax_id']);
        exit(); 
    }else{
        $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `payid` = ?");
        $stmt->bind_param("s", $text);
        $stmt->execute();
        $checkExist = $stmt->get_result();
        $stmt->close();
        
        if($checkExist->num_rows == 0){
            $stmt = $connection->prepare("UPDATE `pays` SET `payid` = ?, `state` = '0' WHERE `hash_id` = ?");
            $stmt->bind_param("ss", $text, $match[1]);
            $stmt->execute();
            $stmt->close();
            
            sendMessage($mainValues['in_review_tax_id'], $removeKeyboard);
            setUser();
            sendMessage($mainValues['reached_main_menu'],getMainKeys());
        }else sendMessage($mainValues['used_tax_id']);
    }

}
if(preg_match('/payWithWeSwap(.*)/',$data,$match)) {
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();

    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    $payInfo = $payInfo->fetch_assoc();

    $fid = $payInfo['plan_id'];
    $type = $payInfo['type'];
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $server_id = $file_detail['server_id'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];

    if($type != "INCREASE_WALLET" && $type != "RENEW_ACCOUNT"){
        if($acount <= 0 and $inbound_id != 0){
            alert($mainValues['out_of_connection_capacity']);
            exit;
        }
        if($inbound_id == 0) {
            $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
            $stmt->bind_param("i", $server_id);
            $stmt->execute();
            $server_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
    
            if($server_info['ucount'] <= 0) {
                alert($mainValues['out_of_server_capacity']);
                exit; 
            }
        }else{
            if($acount <= 0){
                alert($mainValues['out_of_server_capacity']);
                exit();
            }
        }
    }
    
    if($type == "RENEW_ACCOUNT"){
        $oid = $payInfo['plan_id'];
        
        $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `id` = ?");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        $order = $stmt->get_result();
        $stmt->close();
        if($order->num_rows == 0){
            delMessage();
            sendMessage($mainValues['config_not_found'], getMainKeys());
            exit();
        }

    }
    
    delMessage();
    sendMessage($mainValues['please_wait_message'],$removeKeyboard);
    
    
    $price = $payInfo['price'];
    $priceInUSD = round($price / $botState['USDRate'],2);
    $priceInTrx = round($price / $botState['TRXRate'],2);
    $pay = NOWPayments('POST', 'payment', [
        'price_amount' => $priceInUSD,
        'price_currency' => 'usd',
        'pay_currency' => 'trx'
    ]);
    if(isset($pay->pay_address)){
        $payAddress = $pay->pay_address;
        
        $payId = $pay->payment_id;
        
        $stmt = $connection->prepare("UPDATE `pays` SET `payid` = ? WHERE `hash_id` = ?");
        $stmt->bind_param("is", $payId, $match[1]);
        $stmt->execute();
        $stmt->close();
        
        $keys = json_encode(['inline_keyboard'=>[
            [['text'=>"پرداخت با درگاه ارزی ریالی",'url'=>"https://changeto.technology/quick?amount=$priceInTrx&currency=TRX&address=$payAddress"]],
            [['text'=>"پرداخت کردم ✅",'callback_data'=>"havePaiedWeSwap" . $match[1]]]
            ]]);
sendMessage("
✅ لینک پرداخت با موفقیت ایجاد شد

💰مبلغ : " . $priceInTrx . " ترون

✔️ بعد از پرداخت حدود 1 الی 15 دقیقه صبر کنید تا پرداخت به صورت کامل انجام شود سپس روی پرداخت کردم کلیک کنید
⁮⁮ ⁮⁮
",$keys);
    }else{
        if($pay->statusCode == 400){
            sendMessage("مقدار انتخاب شده کمتر از حد مجاز است");
        }else{
            sendMessage("مشکلی رخ داده است، لطفا به پشتیبانی اطلاع بدهید");
        }
        sendMessage("لطفا یکی از کلید های زیر را انتخاب کنید",getMainKeys());
    }
}
if(preg_match('/havePaiedWeSwap(.*)/',$data,$match)) {
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();

    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    $payInfo = $payInfo->fetch_assoc();

    if($payInfo['state'] == "pending"){
    $payid = $payInfo['payid'];
    $payType = $payInfo['type'];
    $price = $payInfo['price'];

    $request_json = NOWPayments('GET', 'payment', $payid);
    if($request_json->payment_status == 'finished' or $request_json->payment_status == 'confirmed' or $request_json->payment_status == 'sending'){
    $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'approved' WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $stmt->close();
        
    if($payType == "INCREASE_WALLET"){
        $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
        $stmt->bind_param("ii", $price, $from_id);
        $stmt->execute();
        $stmt->close();
        
        sendMessage("افزایش حساب شما با موفقیت تأیید شد\n✅ مبلغ " . number_format($price). " تومان به حساب شما اضافه شد");
        sendMessage("✅ مبلغ " . number_format($price) . " تومان به کیف پول کاربر $from_id توسط درگاه ارزی ریالی اضافه شد",null,null,$admin);                
    }
    elseif($payType == "BUY_SUB"){
    $uid = $from_id;
    $fid = $payInfo['plan_id']; 
    $volume = $payInfo['volume'];
    $days = $payInfo['day'];
    $description = $payInfo['description'];
    
    
    $acctxt = '';
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if($volume == 0 && $days == 0){
        $volume = $file_detail['volume'];
        $days = $file_detail['days'];
    }
    
    $date = time();
    $expire_microdate = floor(microtime(true) * 1000) + (864000 * $days * 100);
    $expire_date = $date + (86400 * $days);
    $type = $file_detail['type'];
    $protocol = $file_detail['protocol'];
    $price = $payInfo['price'];   
    
    $server_id = $file_detail['server_id'];
    $netType = $file_detail['type'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];
    $limitip = $file_detail['limitip'];
    $rahgozar = $file_detail['rahgozar'];
    $customPath = $file_detail['custom_path'];
    $customPort = $file_detail['custom_port'];
    $customSni = $file_detail['custom_sni'];
    
    $accountCount = $payInfo['agent_count']!=0?$payInfo['agent_count']:1;
    $eachPrice = $price / $accountCount;
    if($acount == 0 and $inbound_id != 0){
        alert($mainValues['out_of_connection_capacity']);
        exit;
    }
    if($inbound_id == 0) {
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    
        if($server_info['ucount'] <= 0) {
            alert($mainValues['out_of_server_capacity']);
            exit;
        }
    }

    $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverInfo = $stmt->get_result()->fetch_assoc();
    $serverTitle = $serverInfo['title'];
    $srv_remark = $serverInfo['remark'];
    $stmt->close();

    $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverConfig = $stmt->get_result()->fetch_assoc();
    $serverType = $serverConfig['type'];
    $portType = $serverConfig['port_type'];
    $panelUrl = $serverConfig['panel_url'];
    $stmt->close();
    include 'phpqrcode/qrlib.php';

    alert($mainValues['sending_config_to_user']);
    define('IMAGE_WIDTH',540);
    define('IMAGE_HEIGHT',540);
    for($i = 1; $i <= $accountCount; $i++){
        $uniqid = generateRandomString(42,$protocol);
        
        $savedinfo = file_get_contents('settings/temp.txt');
        $savedinfo = explode('-',$savedinfo);
        $port = $savedinfo[0] + 1;
        $last_num = $savedinfo[1] + 1;
        
        if($botState['remark'] == "digits"){
            $rnd = rand(10000,99999);
            $remark = "{$srv_remark}-{$rnd}";
        }
        elseif($botState['remark'] == "manual"){
            $remark = $payInfo['description'];
        }
        else{
            $rnd = rand(1111,99999);
            $remark = "{$srv_remark}-{$from_id}-{$rnd}";
        }
        if(!empty($description)) $remark = $description;
        if($portType == "auto"){
            file_put_contents('settings/temp.txt',$port.'-'.$last_num);
        }else{
            $port = rand(1111,65000);
        }
        
        if($inbound_id == 0){    
            if($serverType == "marzban"){
                $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                if(!$response->success){
                    if(isset($response->msg) && $response->msg == "User already exists"){
                        $remark .= rand(1111,99999);
                        $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                    }
                }
            }else{
                $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid); 
                if(!$response->success){
                    if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();
                    elseif(isset($response->msg) && strstr($response->msg, "Port already exists")) $port = rand(1111,65000);
                    
                    $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid);
                } 
            }
        }else {
            $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid); 
            if(!$response->success){
                if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();

                $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid);
            } 
        }
        
        if(is_null($response)){
            sendMessage('❌ | 🥺 گلم ، اتصال به سرور برقرار نیست لطفا مدیر رو در جریان بزار ...');
            exit;
        }
        if(isset($response->msg) && $response->msg == "inbound not Found"){
            sendMessage("❌ | 🥺 سطر (inbound) با آیدی $inbound_id تو این سرور وجود نداره ، مدیر رو در جریان بزار ...");
        	exit;
        }
        if(!$response->success){
            sendMessage('❌ | 😮 وای خطا داد لطفا سریع به مدیر بگو ...');
            sendMessage("خطای سرور {$serverInfo['title']}:\n\n" . (isset($response->msg)?$response->msg:"Null response"), null, null, $admin);
            exit;
        }
        
        if($serverType == "marzban"){
            $uniqid = $token = str_replace("/sub/", "", $response->obj->subscription_url);
            $subLink = $botState['subLinkState'] == "on"?$panelUrl . $response->obj->subscription_url:"";
            $vraylink = $response->obj->links;
            $vray_link = json_encode($vraylink);
        }else{
            $token = RandomString(30);
            $subLink = $botState['subLinkState']=="on"?$botUrl . "settings/subLink.php?token=" . $token:"";
    
            $vraylink = getConnectionLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id, $rahgozar, $customPath, $customPort, $customSni);
            $vray_link = json_encode($vraylink);
        }
        foreach($vraylink as $link){
        $acc_text = "
        
😍 سفارش جدید شما
📡 پروتکل: $protocol
🔮 نام سرویس: $remark
🔋حجم سرویس: $volume گیگ
⏰ مدت سرویس: $days روز⁮⁮ ⁮⁮
" . ($botState['configLinkState'] != "off" && $serverType != "marzban"?"
💝 config : <code>$link</code>":"");

if($botState['subLinkState'] == "on") $acc_text .= "

🔋 Volume web: <code> $botUrl"."search.php?id=".$uniqid."</code>


🌐 subscription : <code>$subLink</code>
        
        ";
              
            $file = RandomString() .".png";
            $ecc = 'L';
            $pixel_Size = 11;
            $frame_Size = 0;
            
            QRcode::png($link, $file, $ecc, $pixel_Size, $frame_Size);
        	addBorderImage($file);
        	
        	$backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
            $qrImage = imagecreatefrompng($file);
            
            $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
            imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
            imagepng($backgroundImage, $file);
            imagedestroy($backgroundImage);
            imagedestroy($qrImage);

        	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
            unlink($file);
        }
        
        $agentBought = $payInfo['agent_bought'];
        
        $stmt = $connection->prepare("INSERT INTO `orders_list` 
            (`userid`, `token`, `transid`, `fileid`, `server_id`, `inbound_id`, `remark`, `uuid`, `protocol`, `expire_date`, `link`, `amount`, `status`, `date`, `notif`, `rahgozar`, `agent_bought`)
            VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?,1, ?, 0, ?, ?);");
        $stmt->bind_param("ssiiisssisiiii", $uid, $token, $fid, $server_id, $inbound_id, $remark, $uniqid, $protocol, $expire_date, $vray_link, $eachPrice, $date, $rahgozar, $agentBought);
        $stmt->execute();
        $order = $stmt->get_result(); 
        $stmt->close();
    }
    
    if($userInfo['refered_by'] != null){
        $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
        $stmt->execute();
        $inviteAmount = $stmt->get_result()->fetch_assoc()['value']??0;
        $stmt->close();
        $inviterId = $userInfo['refered_by'];
        
        $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
        $stmt->bind_param("ii", $inviteAmount, $inviterId);
        $stmt->execute();
        $stmt->close();
         
        sendMessage("تبریک یکی از زیر مجموعه های شما خرید انجام داد شما مبلغ " . number_format($inviteAmount) . " تومان جایزه دریافت کردید",null,null,$inviterId);
    }
    $keys = json_encode(['inline_keyboard'=>[
        [
            ['text'=>"بنازم خرید جدید ❤️",'callback_data'=>"wizwizch"]
        ],
        ]]);
        
    if($inbound_id == 0) {
        $stmt = $connection->prepare("UPDATE `server_info` SET `ucount` = `ucount` - ? WHERE `id`=?");
        $stmt->bind_param("ii", $accountCount, $server_id);
        $stmt->execute();
        $stmt->close();
    }else{
        $stmt = $connection->prepare("UPDATE `server_plans` SET `acount` = `acount` - ? WHERE id=?");
        $stmt->bind_param("ii", $accountCount, $fid);
        $stmt->execute();
        $stmt->close();
    }
    $msg = str_replace(['SERVERNAME', 'TYPE', 'USER-ID', 'USERNAME', 'NAME', 'PRICE', 'REMARK', 'VOLUME', 'DAYS'],
                [$serverTitle, 'ارزی ریالی', $from_id, $username, $first_name, $price, $remark,$volume, $days], $mainValues['buy_new_account_request']);
    
    sendMessage($msg,$keys,"html", $admin);
}
    elseif($payType == "RENEW_ACCOUNT"){
        $oid = $payInfo['plan_id'];
        $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `id` = ?");
        $stmt->bind_param("i", $oid);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $fid = $order['fileid'];
        $remark = $order['remark'];
        $uuid = $order['uuid']??"0";
        $server_id = $order['server_id'];
        $inbound_id = $order['inbound_id'];
        $expire_date = $order['expire_date'];
        $expire_date = ($expire_date > $time) ? $expire_date : $time;
        
        $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id` = ? AND `active` = 1");
        $stmt->bind_param("i", $fid);
        $stmt->execute();
        $respd = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $name = $respd['title'];
        $days = $respd['days'];
        $volume = $respd['volume'];
        $price = $payInfo['price'];
        
        $stmt = $connection->prepare("SELECT * FROM server_config WHERE id=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $serverType = $server_info['type'];
    
        if($serverType == "marzban"){
            $response = editMarzbanConfig($server_id, ['remark'=>$remark, 'days'=>$days, 'volume' => $volume]);
        }else{
            if($inbound_id > 0)
                $response = editClientTraffic($server_id, $inbound_id, $uuid, $volume, $days, "renew");
            else
                $response = editInboundTraffic($server_id, $uuid, $volume, $days, "renew");
        }
        
        if(is_null($response)){
        	alert('🔻مشکل فنی در اتصال به سرور. لطفا به مدیریت اطلاع بدید',true);
        	exit;
        }
        $stmt = $connection->prepare("UPDATE `orders_list` SET `expire_date` = ?, `notif` = 0 WHERE `id` = ?");
        $newExpire = $time + $days * 86400;
        $stmt->bind_param("ii", $newExpire, $oid);
        $stmt->execute();
        $stmt->close();
        $stmt = $connection->prepare("INSERT INTO `increase_order` VALUES (NULL, ?, ?, ?, ?, ?, ?);");
        $stmt->bind_param("iiisii", $uid, $server_id, $inbound_id, $remark, $price, $time);
        $stmt->execute();
        $stmt->close();
    
    sendMessage("✅سرویس $remark با موفقیت تمدید شد",getMainKeys());
    $keys = json_encode(['inline_keyboard'=>[
        [
            ['text'=>"به به تمدید 😍",'callback_data'=>"wizwizch"]
            ],
        ]]);
    
        $msg = str_replace(['TYPE', "USER-ID", "USERNAME", "NAME", "PRICE", "REMARK", "VOLUME", "DAYS"],['کیف پول', $from_id, $username, $first_name, $price, $remark, $volume, $days], $mainValues['renew_account_request_message']);
    
    sendMessage($msg, $keys,"html", $admin);
    }
    elseif(preg_match('/^INCREASE_DAY_(\d+)_(\d+)/',$payType, $increaseInfo)){
        $orderId = $increaseInfo[1];
        
        $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `id` = ?");
        $stmt->bind_param("i", $orderId);
        $stmt->execute();
        $orderInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $server_id = $orderInfo['server_id'];
        $inbound_id = $orderInfo['inbound_id'];
        $remark = $orderInfo['remark'];
        $uuid = $orderInfo['uuid']??"0";
        
        $planid = $increaseInfo[2];
    
        
        
        $stmt = $connection->prepare("SELECT * FROM `increase_day` WHERE `id` = ?");
        $stmt->bind_param("i", $planid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $price = $payInfo['price'];
        $volume = $res['volume'];
    
        $stmt = $connection->prepare("SELECT * FROM server_config WHERE id=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $serverType = $server_info['type'];
    
        if($serverType == "marzban"){
            $response = editMarzbanConfig($server_id, ['remark'=>$remark, 'plus_day'=>$volume]);
        }else{
            if($inbound_id > 0)
                $response = editClientTraffic($server_id, $inbound_id, $uuid, 0, $volume);
            else
                $response = editInboundTraffic($server_id, $uuid, 0, $volume);
        }
        
    if($response->success){
        $stmt = $connection->prepare("UPDATE `orders_list` SET `expire_date` = `expire_date` + ?, `notif` = 0 WHERE `uuid` = ?");
        $newVolume = $volume * 86400;
        $stmt->bind_param("is", $newVolume, $uuid);
        $stmt->execute();
        $stmt->close();
        
        $stmt = $connection->prepare("INSERT INTO `increase_order` VALUES (NULL, ?, ?, ?, ?, ?, ?);");
        $newVolume = $volume * 86400;
        $stmt->bind_param("iiisii", $from_id, $server_id, $inbound_id, $remark, $price, $time);
        $stmt->execute();
        $stmt->close();
        
        sendMessage("✅$volume روز به مدت زمان سرویس شما اضافه شد",getMainKeys());
        
        $keys = json_encode(['inline_keyboard'=>[
            [
                ['text'=>"اخیش یکی زمان زد 😁",'callback_data'=>"wizwizch"]
                ],
            ]]);
    sendMessage("
    🔋|💰 افزایش زمان با ( کیف پول )
    
    ▫️آیدی کاربر: $from_id
    👨‍💼اسم کاربر: $first_name
    ⚡️ نام کاربری: $username
    🎈 نام سرویس: $remark
    ⏰ مدت افزایش: $volume روز
    💰قیمت: $price تومان
    ⁮⁮ ⁮⁮
    ",$keys,"html", $admin);
    
        exit;
    }else {
        alert("به دلیل مشکل فنی امکان افزایش حجم نیست. لطفا به مدیریت اطلاع بدید یا 5دقیقه دیگر دوباره تست کنید", true);
        exit;
    }
    }
    elseif(preg_match('/^INCREASE_VOLUME_(\d+)_(\d+)/',$payType, $increaseInfo)){
    $orderId = $increaseInfo[1];
    
    $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `id` = ?");
    $stmt->bind_param("i", $orderId);
    $stmt->execute();
    $orderInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $server_id = $orderInfo['server_id'];
    $inbound_id = $orderInfo['inbound_id'];
    $remark = $orderInfo['remark'];
    $uuid = $orderInfo['uuid']??"0";
    
    $planid = $increaseInfo[2];
    
    $stmt = $connection->prepare("SELECT * FROM `increase_plan` WHERE `id` = ?");
    $stmt->bind_param("i", $planid);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $price = $payInfo['price'];
    $volume = $res['volume'];
    
        $stmt = $connection->prepare("SELECT * FROM server_config WHERE id=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $serverType = $server_info['type'];
    
        if($serverType == "marzban"){
            $response = editMarzbanConfig($server_id, ['remark'=>$remark, 'plus_volume'=>$volume]);
        }else{
            if($inbound_id > 0)
                $response = editClientTraffic($server_id, $inbound_id, $uuid, $volume, 0);
            else
                $response = editInboundTraffic($server_id, $uuid, $volume, 0);
        }
        
    if($response->success){
        $stmt = $connection->prepare("UPDATE `orders_list` SET `notif` = 0 WHERE `uuid` = ?");
        $stmt->bind_param("s", $uuid);
        $stmt->execute();
        $stmt->close();
        $keys = json_encode(['inline_keyboard'=>[
            [
                ['text'=>"اخیش یکی حجم زد 😁",'callback_data'=>"wizwizch"]
                ],
            ]]);
    sendMessage("
    🔋|💰 افزایش حجم با ( کیف پول )
    
    ▫️آیدی کاربر: $from_id
    👨‍💼اسم کاربر: $first_name
    ⚡️ نام کاربری: $username
    🎈 نام سرویس: $remark
    ⏰ مدت افزایش: $volume گیگ
    💰قیمت: $price تومان
    ⁮⁮ ⁮⁮
    ",$keys,"html", $admin);
        sendMessage( "✅$volume گیگ به حجم سرویس شما اضافه شد",getMainKeys());exit;
        
    
    }else {
        alert("به دلیل مشکل فنی امکان افزایش حجم نیست. لطفا به مدیریت اطلاع بدید یا 5دقیقه دیگر دوباره تست کنید",true);
        exit;
    }
    }
    elseif($payType == "RENEW_SCONFIG"){
        $uid = $from_id;
        $fid = $payInfo['plan_id']; 
    
        $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
        $stmt->bind_param("i", $fid);
        $stmt->execute();
        $file_detail = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $volume = $file_detail['volume'];
        $days = $file_detail['days'];
        
        $price = $payInfo['price'];   
        $server_id = $file_detail['server_id'];
        $configInfo = json_decode($payInfo['description'],true);
        $remark = $configInfo['remark'];
        $uuid = $configInfo['uuid'];
        $isMarzban = $configInfo['marzban'];
        
        $remark = $payInfo['description'];
        $inbound_id = $payInfo['volume']; 
        
        if($isMarzban){
            $response = editMarzbanConfig($server_id, ['remark'=>$remark, 'days'=>$days, 'volume' => $volume]);
        }else{
            if($inbound_id > 0)
                $response = editClientTraffic($server_id, $inbound_id, $uuid, $volume, $days, "renew");
            else
                $response = editInboundTraffic($server_id, $uuid, $volume, $days, "renew");
        }
        
    	if(is_null($response)){
    		alert('🔻مشکل فنی در اتصال به سرور. لطفا به مدیریت اطلاع بدید',true);
    		exit;
    	}
    	$stmt = $connection->prepare("INSERT INTO `increase_order` VALUES (NULL, ?, ?, ?, ?, ?, ?);");
    	$stmt->bind_param("iiisii", $uid, $server_id, $inbound_id, $remark, $price, $time);
    	$stmt->execute();
    	$stmt->close();
    
        sendMessage("
        🔋|💰 تمدید مشخصات کانفیگ با ( کیف پول )
        
        ▫️آیدی کاربر: $from_id
        👨‍💼اسم کاربر: $first_name
        ⚡️ نام کاربری: $username
        🎈 نام سرویس: $remark
        ⏰ مدت کانفیگ: $volume گیگ
        حجم کانفیگ:  $days روز
        💰قیمت: $price تومان
        ⁮⁮ ⁮⁮
        ",$keys,"html", $admin);
    
    }
        
    editKeys(json_encode(['inline_keyboard'=>[
		    [['text'=>"پرداخت انجام شد",'callback_data'=>"wizwizch"]]
		    ]]));
}else{
    if($request_json->payment_status == 'partially_paid'){
        $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'partiallyPaied' WHERE `hash_id` = ?");
        $stmt->bind_param("s", $match[1]);
        $stmt->execute();
        $stmt->close();
        alert("شما هزینه کمتری پرداخت کردید، لطفا به پشتیبانی پیام بدهید");
    }else{
        alert("پرداخت مورد نظر هنوز تکمیل نشده!");
    }
}
}else alert("این لینک پرداخت منقضی شده است");
}
if($data=="messageToSpeceficUser" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    delMessage();
    sendMessage($mainValues['send_user_id'], $cancelKey);
    setUser($data);
}
if($userInfo['step'] == "messageToSpeceficUser" && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(!is_numeric($text)){
        sendMessage($mainValues['send_only_number']);
        exit();
    }
    $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid` = ?");
    $stmt->bind_param("i", $text);
    $stmt->execute();
    $usersCount = $stmt->get_result()->num_rows;
    $stmt->close();

    if($usersCount > 0 ){
        sendMessage("👀| خصوصی میخوای بهش پیام بدی شیطون، پیامت رو بفرس تا در گوشش بگم:");
        setUser("sendMessageToUser" . $text);
    }else{
        sendMessage($mainValues['user_not_found']);
    }
}
if($data == 'message2All' and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `send_list` WHERE `state` = 1");
    $stmt->execute();
    $info = $stmt->get_result();
    $stmt->close();
    
    if($info->num_rows > 0){
        $sendInfo = $info->fetch_assoc();
        
        $offset = $sendInfo['offset']??0;
        $type = $sendInfo['type'];
        
        $stmt = $connection->prepare("SELECT * FROM `users`");
        $stmt->execute();
        $usersCount = $stmt->get_result()->num_rows;
        $stmt->close();

        $leftMessages = $usersCount - $offset;
        
        if($type == "forwardall"){
            sendMessage("
            ❗️ یک فروارد همگانی در صف انتشار می باشد لطفا صبور باشید ...
            
            🔰 تعداد کاربران : $usersCount
            ☑️ فروارد شده : $offset
            📣 باقیمانده : $leftMessages
            ⁮⁮ ⁮⁮ ⁮⁮ ⁮⁮
            ");
        }else{
            sendMessage("
            ❗️ یک پیام همگانی در صف انتشار می باشد لطفا صبور باشید ...
            
            🔰 تعداد کاربران : $usersCount
            ☑️ ارسال شده : $offset
            📣 باقیمانده : $leftMessages
            ⁮⁮ ⁮⁮ ⁮⁮ ⁮⁮
            ");
        }
    }else{
        setUser('s2a');
        sendMessage("لطفا پیامت رو بنویس ، میخوام برا همه بفرستمش: 🙂",$cancelKey);
    }
}
if($userInfo['step'] == 's2a' and $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser();

    if($fileid !== null) {
        $stmt = $connection->prepare("INSERT INTO `send_list` (`type`, `text`, `file_id`) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $filetype, $caption, $fileid);
    }
    else{
        $stmt = $connection->prepare("INSERT INTO `send_list` (`type`, `text`) VALUES ('text', ?)");
        $stmt->bind_param("s", $text);
    }
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();
    
    sendMessage('⏳ مرسی از پیامت  ...  ',$removeKeyboard);
    sendMessage("برای همه بفرستم؟",json_encode(['inline_keyboard'=>[
    [['text'=>"بفرست",'callback_data'=>"yesSend2All" . $id],['text'=>"نه نفرست",'callback_data'=>"noDontSend2all" . $id]]
    ]]));
}
if(preg_match('/^noDontSend2all(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("DELETE FROM `send_list` WHERE `id` = ?");
    $stmt->bind_param('i', $match[1]);
    $stmt->exeucte();
    $stmt->close();
    
    editText($message_id,'ارسال پیام همگانی لغو شد',getMainKeys());
}
if(preg_match('/^yesSend2All(\d+)/', $data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("UPDATE `send_list` SET `state` = 1 WHERE `id` = ?") ;
    $stmt->bind_param('i', $match[1]);
    $stmt->execute();
    $stmt->close();
    
    editText($message_id,'⏳ کم کم برا همه ارسال میشه ...  ',getMainKeys());
}
if($data=="forwardToAll" && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `send_list` WHERE `state` = 1");
    $stmt->execute();
    $info = $stmt->get_result();
    $stmt->close();
    
    if($info->num_rows > 0){
        $sendInfo = $info->fetch_assoc();
        $offset = $sendInfo['offset']??0;
        $type = $sendInfo['type'];
        
        $stmt = $connection->prepare("SELECT * FROM `users`");
        $stmt->execute();
        $usersCount = $stmt->get_result()->num_rows;
        $stmt->close();
        
        $leftMessages = $usersCount - $offset;
        
        if($type == "forwardall"){
            sendMessage("
            ❗️ یک فروارد همگانی در صف انتشار می باشد لطفا صبور باشید ...
            
            🔰 تعداد کاربران : $usersCount
            ☑️ فروارد شده : $offset
            📣 باقیمانده : $leftMessages
            ⁮⁮ ⁮⁮ ⁮⁮ ⁮⁮
            ");
        }else{
            sendMessage("
            ❗️ یک پیام همگانی در صف انتشار می باشد لطفا صبور باشید ...
            
            🔰 تعداد کاربران : $usersCount
            ☑️ ارسال شده : $offset
            📣 باقیمانده : $leftMessages
            ⁮⁮ ⁮⁮ ⁮⁮ ⁮⁮
            ");
        }
    }else{
        delMessage();
        sendMessage($mainValues['forward_your_message'], $cancelKey);
        setUser($data);
    }
}
if($userInfo['step'] == "forwardToAll" && ($from_id == $admin || $userInfo['isAdmin'] == true) && $text != $buttonValues['cancel']){
    $stmt = $connection->prepare("INSERT INTO `send_list` (`type`, `message_id`, `chat_id`) VALUES ('forwardall', ?, ?)");
    $stmt->bind_param('ss', $message_id, $chat_id);
    $stmt->execute();
    $id = $stmt->insert_id;
    $stmt->close();

    setUser();
    sendMessage('⏳ مرسی از پیامت  ...  ',$removeKeyboard);
    sendMessage("برای همه فروارد کنم؟",json_encode(['inline_keyboard'=>[
    [['text'=>"بفرست",'callback_data'=>"yesSend2All" . $id],['text'=>"نه نفرست",'callback_data'=>"noDontSend2all" . $id]]
    ]]));
}
if(preg_match('/selectServer(?<serverId>\d+)_(?<buyType>\w+)/',$data, $match) && ($botState['sellState']=="on" || ($from_id == $admin || $userInfo['isAdmin'] == true)) ) {
    $sid = $match['serverId'];
        
    $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `parent`=0 order by `id` asc");
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    if($respd->num_rows == 0){
        alert($mainValues['category_not_avilable']);
    }else{
        
        $keyboard = [];
        while ($file = $respd->fetch_assoc()){
            $id = $file['id'];
            $name = $file['title'];
            $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `server_id`=? and `catid`=? and `active`=1");
            $stmt->bind_param("ii", $sid, $id);
            $stmt->execute();
            $rowcount = $stmt->get_result()->num_rows; 
            $stmt->close();
            if($rowcount>0) $keyboard[] = ['text' => "$name", 'callback_data' => "selectCategory{$id}_{$sid}_{$match['buyType']}"];
        }
        if(empty($keyboard)){
            alert($mainValues['category_not_avilable']);exit;
        }
        alert($mainValues['receive_categories']);

        $keyboard[] = ['text' => $buttonValues['back_to_main'], 'callback_data' => 
        ($match['buyType'] == "one"?"agentOneBuy":($match['buyType'] == "much"?"agentMuchBuy":"buySubscription"))];
        $keyboard = array_chunk($keyboard,1);
        editText($message_id,$mainValues['buy_sub_select_category'], json_encode(['inline_keyboard'=>$keyboard]));
    }

}
if(preg_match('/selectCategory(?<categoryId>\d+)_(?<serverId>\d+)_(?<buyType>\w+)/',$data,$match) && ($botState['sellState']=="on" || $from_id == $admin || $userInfo['isAdmin'] == true)) {
    $call_id = $match['categoryId'];
    $sid = $match['serverId'];
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `server_id`=? and `price` != 0 and `catid`=? and `active`=1 order by `id` asc");
    $stmt->bind_param("ii", $sid, $call_id);
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    if($respd->num_rows==0){
        alert($mainValues['no_plan_available']); 
    }else{
        alert($mainValues['receive_plans']);
        $keyboard = [];
        while($file = $respd->fetch_assoc()){
            $id = $file['id'];
            $name = $file['title'];
            $price = $file['price'];
            if($userInfo['is_agent'] == true && ($match['buyType'] == "one" || $match['buyType'] == "much")){
                $discounts = json_decode($userInfo['discount_percent'],true);
                if($botState['agencyPlanDiscount']=="on") $discount = $discounts['plans'][$id]?? $discounts['normal'];
                else $discount = $discounts['servers'][$sid]?? $discounts['normal'];
                
                $price -= floor($price * $discount / 100);
            }
            $price = ($price == 0) ? 'رایگان' : number_format($price).' تومان ';
            $keyboard[] = ['text' => "$name - $price", 'callback_data' => "selectPlan{$id}_{$call_id}_{$match['buyType']}"];
        }
        if($botState['plandelkhahState'] == "on" && $match['buyType'] != "much"){
	        $keyboard[] = ['text' => $mainValues['buy_custom_plan'], 'callback_data' => "selectCustomPlan{$call_id}_{$sid}_{$match['buyType']}"];
        }
        $keyboard[] = ['text' => $buttonValues['back_to_main'], 'callback_data' => "selectServer{$sid}_{$match['buyType']}"];
        $keyboard = array_chunk($keyboard,1);
        editText($message_id,$mainValues['buy_sub_select_plan'], json_encode(['inline_keyboard'=>$keyboard]));
    }

}
if(preg_match('/selectCustomPlan(?<categoryId>\d+)_(?<serverId>\d+)_(?<buyType>\w+)/',$data,$match) && ($botState['sellState']=="on" || $from_id == $admin || $userInfo['isAdmin'] == true)) {
    $call_id = $match['categoryId'];
    $sid = $match['serverId'];
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `server_id`=? and `catid`=? and `active`=1 order by `id` asc");
    $stmt->bind_param("ii", $sid, $call_id);
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    alert($mainValues['receive_plans']);
    $keyboard = [];
    while($file = $respd->fetch_assoc()){
        $id = $file['id'];
        $name = preg_replace("/پلن\s(\d+)\sگیگ\s/","",$file['title']);
        $keyboard[] = ['text' => "$name", 'callback_data' => "selectCustomePlan{$id}_{$call_id}_{$match['buyType']}"];
    }
    $keyboard[] = ['text' => $buttonValues['back_to_main'], 'callback_data' => "selectServer{$sid}_{$match['buyType']}"];
    $keyboard = array_chunk($keyboard,1);
    editText($message_id, $mainValues['select_one_plan_to_edit'], json_encode(['inline_keyboard'=>$keyboard]));

}
if(preg_match('/selectCustomePlan(?<planId>\d+)_(?<categoryId>\d+)_(?<buyType>\w+)/',$data, $match) && ($botState['sellState']=="on" ||$from_id ==$admin)){
	delMessage();
	$price = $botState['gbPrice'];
	if($match['buyType'] == "one" && $userInfo['is_agent'] == true){ 
        $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id` = ?");
        $stmt->bind_param("i", $match[1]);
        $stmt->execute();
        $serverId = $stmt->get_result()->fetch_assoc()['server_id'];
        $stmt->close();

        $discounts = json_decode($userInfo['discount_percent'],true);
        if($botState['agencyPlanDiscount']=="on") $discount = $discounts['plans'][$match[1]]?? $discounts['normal'];
        else $discount = $discounts['servers'][$serverId]?? $discounts['normal'];
        $price -= floor($price * $discount / 100);
	}
	sendMessage(str_replace("VOLUME-PRICE", $price, $mainValues['customer_custome_plan_volume']),$cancelKey);
	setUser("selectCustomPlanGB" . $match[1] . "_" . $match[2] . "_" . $match['buyType']);
}
if(preg_match('/selectCustomPlanGB(?<planId>\d+)_(?<categoryId>\d+)_(?<buyType>\w+)/',$userInfo['step'], $match) && ($botState['sellState']=="on" ||$from_id == $admin) && $text != $buttonValues['cancel']){
    if(!is_numeric($text)){
        sendMessage("😡|لطفا فقط عدد ارسال کن");
        exit();
    }
    elseif($text <1){
        sendMessage("لطفا عددی بزرگتر از 0 وارد کن");
        exit();
    }
    elseif(strstr($text,".")){
        sendMessage(" عدد اعشاری مجاز نیست");
        exit();
    }
    elseif(substr($text, 0, 1) == '0'){
        sendMessage("❌عدد وارد شده نمیتواند با 0 شروع شود!");
        exit();
    }
    
    $id = $match['planId'];
    $price = $botState['dayPrice'];
	if($match['buyType'] == "one" && $userInfo['is_agent'] == true){
        $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id` = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $serverId = $stmt->get_result()->fetch_assoc()['server_id'];
        $stmt->close();

        $discounts = json_decode($userInfo['discount_percent'],true);
        if($botState['agencyPlanDiscount']=="on") $discount = $discounts['plans'][$id]?? $discounts['normal'];
        else $discount = $discounts['servers'][$serverId]?? $discounts['normal'];
        $price -= floor($price * $discount / 100);
	}
    
	sendMessage(str_replace("DAY-PRICE", $price, $mainValues['customer_custome_plan_day']));
	setUser("selectCustomPlanDay" . $id . "_" . $match['categoryId'] . "_" . $text . "_" . $match['buyType']);
}
if((preg_match('/selectCustomPlanDay(?<planId>\d+)_(?<categoryId>\d+)_(?<accountCount>\d+)_(?<buyType>\w+)/',$userInfo['step'], $match)) && ($botState['sellState']=="on" ||$from_id == $admin) && $text != $buttonValues['cancel']){
    if(!is_numeric($text)){
        sendMessage("😡|لطفا فقط عدد ارسال کن");
        exit();
    }
    elseif($text <1){
        sendMessage("لطفا عددی بزرگتر از 0 وارد کن");
        exit();
    }
    elseif(strstr($text,".")){
        sendMessage("عدد اعشاری مجاز نیست");
        exit();
    }
    elseif(substr($text, 0, 1) == '0'){
        sendMessage("❌عدد وارد شده نمیتواند با 0 شروع شود!");
        exit();
    }

	sendMessage($mainValues['customer_custome_plan_name']);
	setUser("enterCustomPlanName" . $match['planId'] . "_" . $match['categoryId'] . "_" . $match['accountCount'] . "_" . $text . "_" . $match['buyType']);
}
if((preg_match('/^discountCustomPlanDay(\d+)/',$userInfo['step'], $match) || preg_match('/enterCustomPlanName(\d+)_(\d+)_(\d+)_(\d+)_(?<buyType>\w+)/',$userInfo['step'], $match)) && ($botState['sellState']=="on" ||$from_id ==$admin) && $text != $buttonValues['cancel']){
    if(preg_match('/^discountCustomPlanDay/', $userInfo['step'])){
        $rowId = $match[1];

        $time = time();
        $stmt = $connection->prepare("SELECT * FROM `discounts` WHERE (`expire_date` > $time OR `expire_date` = 0) AND (`expire_count` > 0 OR `expire_count` = -1) AND `hash_id` = ?");
        $stmt->bind_param("s", $text);
        $stmt->execute();
        $list = $stmt->get_result();
        $stmt->close();
        
        $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `id` = ?");
        $stmt->bind_param("i", $rowId);
        $stmt->execute();
        $payInfo = $stmt->get_result()->fetch_assoc();
        $hash_id = $payInfo['hash_id'];
        $price = $payInfo['price'];
        $id = $payInfo['type'];
    	$volume = $payInfo['volume'];
        $days = $payInfo['day'];
        $stmt->close();
            
        if($list->num_rows>0){
            $discountInfo = $list->fetch_assoc();
            $amount = $discountInfo['amount'];
            $type = $discountInfo['type'];
            $count = $discountInfo['expire_count'];
            $usedBy = !is_null($discountInfo['used_by'])?json_decode($discountInfo['used_by'],true):array();
            
            $canUse = $discountInfo['can_use'];
            $userUsedCount = array_count_values($usedBy)[$from_id];
            if($canUse > $userUsedCount){
                $usedBy[] = $from_id;
                $encodeUsedBy = json_encode($usedBy);
                
                if ($count != -1) $query = "UPDATE `discounts` SET `expire_count` = `expire_count` - 1, `used_by` = ? WHERE `id` = ?";
                else $query = "UPDATE `discounts` SET `used_by` = ? WHERE `id` = ?";
            
                $stmt = $connection->prepare($query);
                $stmt->bind_param("si", $encodeUsedBy, $discountInfo['id']);
                $stmt->execute();
                $stmt->close();
                
                if($type == "percent"){
                    $discount = $price * $amount / 100;
                    $price -= $discount;
                    $discount = number_format($discount) . " تومان";
                }else{
                    $price -= $amount;
                    $discount = number_format($amount) . " تومان";
                }
                if($price < 0) $price = 0;
                
                $stmt = $connection->prepare("UPDATE `pays` SET `price` = ? WHERE `id` = ?");
                $stmt->bind_param("ii", $price, $rowId);
                $stmt->execute();
                $stmt->close();
                sendMessage(str_replace("AMOUNT", $discount, $mainValues['valid_discount_code']));
                $keys = json_encode(['inline_keyboard'=>[
                    [
                        ['text'=>"❤️", "callback_data"=>"wizwizch"]
                        ],
                    ]]);
            sendMessage(
                str_replace(['USERID', 'USERNAME', "NAME", "AMOUNT", "DISCOUNTCODE"], [$from_id, $username, $first_name, $discount, $text], $mainValues['used_discount_code'])
                ,$keys,null,$admin);
                }else sendMessage($mainValues['not_valid_discount_code']);
        }else sendMessage($mainValues['not_valid_discount_code']);
    }else{
        $id = $match[1];
    	$call_id = $match[2];
    	$volume = $match[3];
        $days = $match[4];
        if($match['buyType'] != "much"){
            if(preg_match('/^[a-z]+[0-9]+$/',$text)){} else{
                sendMessage($mainValues['incorrect_config_name']);
                exit();
            }
        }
    }
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=? and `active`=1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $respd = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `id`=?");
    $stmt->bind_param("i", $respd['catid']);
    $stmt->execute();
    $catname = $stmt->get_result()->fetch_assoc()['title'];
    $stmt->close();
    
    $name = $catname." ".$respd['title'];
    $desc = $respd['descr'];
	$sid = $respd['server_id'];
	$keyboard = array();
    $token = base64_encode("{$from_id}.{$id}");

    if(!preg_match('/^discountCustomPlanDay/', $userInfo['step'])){
        $discountPrice = 0;
        $gbPrice = $botState['gbPrice'];
        $dayPrice = $botState['dayPrice'];
        
        if($userInfo['is_agent'] == true && $match['buyType'] == "one") {
            $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id` = ?");
            $stmt->bind_param("i", $match[1]);
            $stmt->execute();
            $serverId = $stmt->get_result()->fetch_assoc()['server_id'];
            $stmt->close();
            
            $discounts = json_decode($userInfo['discount_percent'],true);
            if($botState['agencyPlanDiscount']=="on") $discount = $discounts['plans'][$id]?? $discounts['normal'];
            else $discount = $discounts['servers'][$sid]?? $discounts['normal'];
            
            $gbPrice -= floor($gbPrice * $discount /100);
            $dayPrice -= floor($dayPrice * $discount / 100);
        }
        
        $agentBought = false;
        if($userInfo['is_agent'] == 1 && ($match['buyType'] == "one" || $match['buyType'] == "much")) {
            $agentBought = true;
        }
        
        $price =  ($volume * $gbPrice) + ($days * $dayPrice);
        $hash_id = RandomString();
        $stmt = $connection->prepare("DELETE FROM `pays` WHERE `user_id` = ? AND `type` = 'BUY_SUB' AND `state` = 'pending'");
        $stmt->bind_param("i", $from_id);
        $stmt->execute();
        $stmt->close();
        
        $time = time();
        $stmt = $connection->prepare("INSERT INTO `pays` (`hash_id`, `description`, `user_id`, `type`, `plan_id`, `volume`, `day`, `price`, `request_date`, `state`, `agent_bought`)
                                    VALUES (?, ?, ?, 'BUY_SUB', ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->bind_param("ssiiiiiii", $hash_id, $text, $from_id, $id, $volume, $days, $price, $time, $agentBought);
        $stmt->execute();
        $rowId = $stmt->insert_id;
        $stmt->close();
    }
    
    
    if($botState['cartToCartState'] == "on") $keyboard[] = [['text' => $buttonValues['cart_to_cart'],  'callback_data' => "payCustomWithCartToCart$hash_id"]];
    if($botState['nowPaymentOther'] == "on") $keyboard[] = [['text' => $buttonValues['now_payment_gateway'],  'url' => $botUrl . "pay/?nowpayment&hash_id=" . $hash_id]];
    if($botState['zarinpal'] == "on") $keyboard[] = [['text' => $buttonValues['zarinpal_gateway'],  'url' => $botUrl . "pay/?zarinpal&hash_id=" . $hash_id]];
    if($botState['nextpay'] == "on") $keyboard[] = [['text' => $buttonValues['nextpay_gateway'],  'url' => $botUrl . "pay/?nextpay&hash_id=" . $hash_id]];
    if($botState['weSwapState'] == "on") $keyboard[] = [['text' => $buttonValues['weswap_gateway'],  'callback_data' => "payWithWeSwap" . $hash_id]];
    if($botState['walletState'] == "on") $keyboard[] = [['text' => $buttonValues['pay_with_wallet'],  'callback_data' => "payCustomWithWallet$hash_id"]];
    if($botState['tronWallet'] == "on") $keyboard[] = [['text' => $buttonValues['tron_gateway'],  'callback_data' => "payWithTronWallet" . $hash_id]];

    if(!preg_match('/^discountCustomPlanDay/', $userInfo['step'])) $keyboard[] = [['text' => " 🎁 نکنه کد تخفیف داری؟ ",  'callback_data' => "haveDiscountCustom_" . $rowId]];
	$keyboard[] = [['text' => $buttonValues['cancel'], 'callback_data' => "mainMenu"]];
    $price = ($price == 0) ? 'رایگان' : number_format($price).' تومان ';
    sendMessage(str_replace(['VOLUME', 'DAYS', 'PLAN-NAME', 'PRICE', 'DESCRIPTION'], [$volume, $days, $name, $price, $desc], $mainValues['buy_subscription_detail']),json_encode(['inline_keyboard'=>$keyboard]), "HTML");
    setUser();
}
if(preg_match('/^haveDiscount(.+?)_(.*)/',$data,$match)){
    delMessage();
    sendMessage($mainValues['insert_discount_code'],$cancelKey);
    if($match[1] == "Custom") setUser('discountCustomPlanDay' . $match[2]);
    elseif($match[1] == "SelectPlan") setUser('discountSelectPlan' . $match[2]);
    elseif($match[1] == "Renew") setUser('discountRenew' . $match[2]);
}
if($data=="getTestAccount"){
    if($userInfo['freetrial'] != null && $from_id != $admin && $userInfo['isAdmin'] != true){
        alert("شما اکانت تست را قبلا استفاده کرده اید");
        exit();
    }
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `price`=0");
    $stmt->execute();
    $respd = $stmt->get_result();
    $stmt->close();
    
    if($respd->num_rows > 0){
        alert($mainValues['receving_information']);
    	$keyboard = array();
        while ($row = $respd->fetch_assoc()){
            $id = $row['id'];
            $catInfo = $connection->prepare("SELECT * FROM `server_categories` WHERE `id`=?");
            $catInfo->bind_param("i", $row['catid']);
            $catInfo->execute();
            $catname = $catInfo->get_result()->fetch_assoc()['title'];
            $catInfo->close();
            
            $name = $catname." ".$row['title'];
            $price =  $row['price'];
            $desc = $row['descr'];
        	$sid = $row['server_id'];

            $keyboard[] = [['text' => $name, 'callback_data' => "freeTrial{$id}_normal"]];

        }
    	$keyboard[] = [['text' => $buttonValues['back_to_main'], 'callback_data' => "mainMenu"]];
        editText($message_id,"لطفا یکی از کلید های زیر را انتخاب کنید", json_encode(['inline_keyboard'=>$keyboard]), "HTML");
    }else alert("این بخش موقتا غیر فعال است");
}
if((preg_match('/^discountSelectPlan(\d+)_(\d+)_(\d+)/',$userInfo['step'],$match) || 
    preg_match('/selectPlan(\d+)_(\d+)_(?<buyType>\w+)/',$userInfo['step'], $match) || 
    preg_match('/enterAccountName(\d+)_(\d+)_(?<buyType>\w+)/',$userInfo['step'], $match) || 
    preg_match('/selectPlan(\d+)_(\d+)_(?<buyType>\w+)/',$data, $match)) && 
    ($botState['sellState']=="on" ||$from_id ==$admin) && 
    $text != $buttonValues['cancel']){
    if(preg_match('/^discountSelectPlan/', $userInfo['step'])){
        $rowId = $match[3];
        
        $time = time();
        $stmt = $connection->prepare("SELECT * FROM `discounts` WHERE (`expire_date` > $time OR `expire_date` = 0) AND (`expire_count` > 0 OR `expire_count` = -1) AND `hash_id` = ?");
        $stmt->bind_param("s", $text);
        $stmt->execute();
        $list = $stmt->get_result();
        $stmt->close();
        
        $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `id` = ?");
        $stmt->bind_param("i", $rowId);
        $stmt->execute();
        $payInfo = $stmt->get_result()->fetch_assoc();
        $hash_id = $payInfo['hash_id'];
        $afterDiscount = $payInfo['price'];
        $stmt->close();
        
        if($list->num_rows>0){
            $discountInfo = $list->fetch_assoc();
            $amount = $discountInfo['amount'];
            $type = $discountInfo['type'];
            $count = $discountInfo['expire_count'];
            $canUse = $discountInfo['can_use'];
            $usedBy = !is_null($discountInfo['used_by'])?json_decode($discountInfo['used_by'],true):array();
            $userUsedCount = array_count_values($usedBy)[$from_id];
            if($canUse > $userUsedCount){
                $usedBy[] = $from_id;
                $encodeUsedBy = json_encode($usedBy);
                
                if ($count != -1) $query = "UPDATE `discounts` SET `expire_count` = `expire_count` - 1, `used_by` = ? WHERE `id` = ?";
                else $query = "UPDATE `discounts` SET `used_by` = ? WHERE `id` = ?";
    
                $stmt = $connection->prepare($query);
                $stmt->bind_param("si", $encodeUsedBy, $discountInfo['id']);
                $stmt->execute();
                $stmt->close();
                
                if($type == "percent"){
                    $discount = $afterDiscount * $amount / 100;
                    $afterDiscount -= $discount;
                    $discount = number_format($discount) . " تومان";
                }else{
                    $afterDiscount -= $amount;
                    $discount = number_format($amount) . " تومان";
                }
                if($afterDiscount < 0) $afterDiscount = 0;
                
                $stmt = $connection->prepare("UPDATE `pays` SET `price` = ? WHERE `id` = ?");
                $stmt->bind_param("ii", $afterDiscount, $rowId);
                $stmt->execute();
                $stmt->close();
                sendMessage(str_replace("AMOUNT", $discount, $mainValues['valid_discount_code']));
                $keys = json_encode(['inline_keyboard'=>[
                    [
                        ['text'=>"❤️", "callback_data"=>"wizwizch"]
                        ],
                    ]]);
                sendMessage(
                    str_replace(['USERID', 'USERNAME', "NAME", "AMOUNT", "DISCOUNTCODE"], [$from_id, $username, $first_name, $discount, $text], $mainValues['used_discount_code'])
                    ,$keys,null,$admin);
            }else sendMessage($mainValues['not_valid_discount_code']);
        }else sendMessage($mainValues['not_valid_discount_code']);
        setUser();
    }elseif(isset($data)) delMessage();


    if($botState['remark'] ==  "manual" && preg_match('/^selectPlan/',$data) && $match['buyType'] != "much"){
        sendMessage($mainValues['customer_custome_plan_name'], $cancelKey);
        setUser('enterAccountName' . $match[1] . "_" . $match[2] . "_" . $match['buyType']);
        exit();
    }

    $remark = "";
    if(preg_match("/selectPlan(\d+)_(\d+)_(\w+)/",$userInfo['step'])){
        if($match['buyType'] == "much"){
            if(is_numeric($text)){
                if($text > 0){
                    $accountCount = $text;
                    setUser();
                }else{sendMessage( $mainValues['send_positive_number']); exit(); }
            }else{ sendMessage($mainValues['send_only_number']); exit(); }
        }        
    }
    elseif(preg_match("/enterAccountName(\d+)_(\d+)/",$userInfo['step'])){
        if(preg_match('/^[a-z]+[0-9]+$/',$text)){
            $remark = $text;
            setUser();
        } else{
            sendMessage($mainValues['incorrect_config_name']);
            exit();
        }
    }
    else{
        if($match['buyType'] == "much"){
            setUser($data);
            sendMessage($mainValues['enter_account_amount'], $cancelKey);
            exit();
        }
    }
    
    
    $id = $match[1];
	$call_id = $match[2];
    alert($mainValues['receving_information']);
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=? and `active`=1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $respd = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `id`=?");
    $stmt->bind_param("i", $respd['catid']);
    $stmt->execute();
    $catname = $stmt->get_result()->fetch_assoc()['title'];
    $stmt->close();
    
    $name = $catname." ".$respd['title'];
    $desc = $respd['descr'];
	$sid = $respd['server_id'];
	$keyboard = array();
    $price =  $respd['price'];
    if(isset($accountCount)) $price *= $accountCount;
    
    $agentBought = false;
    if($userInfo['is_agent'] == true && ($match['buyType'] == "one" || $match['buyType'] == "much")){
        $discounts = json_decode($userInfo['discount_percent'],true);
        if($botState['agencyPlanDiscount']=="on") $discount = $discounts['plans'][$id]?? $discounts['normal'];
        else $discount = $discounts['servers'][$sid]?? $discounts['normal'];
        $price -= floor($price * $discount / 100);

        $agentBought = true;
    }
    if($price == 0 or ($from_id == $admin)){
        $keyboard[] = [['text' => '📥 دریافت رایگان', 'callback_data' => "freeTrial{$id}_{$match['buyType']}"]];
        setUser($remark, 'temp');
    }else{
        $token = base64_encode("{$from_id}.{$id}");
        
        if(!preg_match('/^discountSelectPlan/', $userInfo['step'])){
            $hash_id = RandomString();
            $stmt = $connection->prepare("DELETE FROM `pays` WHERE `user_id` = ? AND `type` = 'BUY_SUB' AND `state` = 'pending'");
            $stmt->bind_param("i", $from_id);
            $stmt->execute();
            $stmt->close();
            
            $time = time();
            if(isset($accountCount)){
                $stmt = $connection->prepare("INSERT INTO `pays` (`hash_id`, `user_id`, `type`, `plan_id`, `volume`, `day`, `price`, `request_date`, `state`, `agent_bought`, `agent_count`)
                                            VALUES (?, ?, 'BUY_SUB', ?, '0', '0', ?, ?, 'pending', ?, ?)");
                $stmt->bind_param("siiiiii", $hash_id, $from_id, $id, $price, $time, $agentBought, $accountCount);
            }else{
                $stmt = $connection->prepare("INSERT INTO `pays` (`hash_id`, `description`, `user_id`, `type`, `plan_id`, `volume`, `day`, `price`, `request_date`, `state`, `agent_bought`)
                                            VALUES (?, ?, ?, 'BUY_SUB', ?, '0', '0', ?, ?, 'pending', ?)");
                $stmt->bind_param("ssiiiii", $hash_id, $remark, $from_id, $id, $price, $time, $agentBought);
            }
            $stmt->execute();
            $rowId = $stmt->insert_id;
            $stmt->close();
        }else{
            $price = $afterDiscount;
        }
        
        if($botState['cartToCartState'] == "on") $keyboard[] = [['text' => $buttonValues['cart_to_cart'],  'callback_data' => "payWithCartToCart$hash_id"]];
        if($botState['nowPaymentOther'] == "on") $keyboard[] = [['text' => $buttonValues['now_payment_gateway'],  'url' => $botUrl . "pay/?nowpayment&hash_id=" . $hash_id]];
        if($botState['zarinpal'] == "on") $keyboard[] = [['text' => $buttonValues['zarinpal_gateway'],  'url' => $botUrl . "pay/?zarinpal&hash_id=" . $hash_id]];
        if($botState['nextpay'] == "on") $keyboard[] = [['text' => $buttonValues['nextpay_gateway'],  'url' => $botUrl . "pay/?nextpay&hash_id=" . $hash_id]];
        if($botState['weSwapState'] == "on") $keyboard[] = [['text' => $buttonValues['weswap_gateway'],  'callback_data' => "payWithWeSwap" . $hash_id]];
        if($botState['walletState'] == "on") $keyboard[] = [['text' => $buttonValues['pay_with_wallet'],  'callback_data' => "payWithWallet$hash_id"]];
        if($botState['tronWallet'] == "on") $keyboard[] = [['text' => $buttonValues['tron_gateway'],  'callback_data' => "payWithTronWallet" . $hash_id]];
        
        if(!preg_match('/^discountSelectPlan/', $userInfo['step'])) $keyboard[] = [['text' => " 🎁 نکنه کد تخفیف داری؟ ",  'callback_data' => "haveDiscountSelectPlan_" . $match[1] . "_" . $match[2] . "_" . $rowId]];

    }
	$keyboard[] = [['text' => $buttonValues['back_to_main'], 'callback_data' => "selectCategory{$call_id}_{$sid}_{$match['buyType']}"]];
    $priceC = ($price == 0) ? 'رایگان' : number_format($price).' تومان ';
    if(isset($accountCount)){
        $eachPrice = number_format($price / $accountCount) . " تومان";
        $msg = str_replace(['ACCOUNT-COUNT', 'TOTAL-PRICE', 'PLAN-NAME', 'PRICE', 'DESCRIPTION'], [$accountCount, $priceC, $name, $eachPrice, $desc], $mainValues['buy_much_subscription_detail']);
    }
    else $msg = str_replace(['PLAN-NAME', 'PRICE', 'DESCRIPTION'], [$name, $priceC, $desc], $mainValues['buy_subscription_detail']);
    sendMessage($msg, json_encode(['inline_keyboard'=>$keyboard]), "HTML");
}
if(preg_match('/payCustomWithWallet(.*)/',$data, $match)){
    setUser();
    
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();

    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    $payInfo = $payInfo->fetch_assoc();
    
    if($payInfo['state'] == "paid_with_wallet") exit();

    $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'paid_with_wallet' WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $stmt->close();
    
    $uid = $from_id;
    $fid = $payInfo['plan_id']; 
    $volume = $payInfo['volume'];
    $days = $payInfo['day'];
    
    $acctxt = '';
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $date = time();
    $expire_microdate = floor(microtime(true) * 1000) + (864000 * $days * 100);
    $expire_date = $date + (86400 * $days);
    $type = $file_detail['type'];
    $protocol = $file_detail['protocol'];
    $price = $payInfo['price'];

    if($userInfo['wallet'] < $price){
        alert("موجودی حساب شما کم است");
        exit();
    }
    
    
    $server_id = $file_detail['server_id'];
    $netType = $file_detail['type'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];
    $limitip = $file_detail['limitip'];
    $rahgozar = $file_detail['rahgozar'];
    $customPath = $file_detail['custom_path'];
    $customPort = $file_detail['custom_port'];
    $customSni = $file_detail['custom_sni'];


    if($acount == 0 and $inbound_id != 0){
        alert($mainValues['out_of_connection_capacity']);
        exit;
    }
    if($inbound_id == 0) {
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($server_info['ucount'] <= 0) {
            alert($mainValues['out_of_server_capacity']);
            exit;
        }
    }

    $uniqid = generateRandomString(42,$protocol); 

    $savedinfo = file_get_contents('settings/temp.txt');
    $savedinfo = explode('-',$savedinfo);
    $port = $savedinfo[0] + 1;
    $last_num = $savedinfo[1] + 1;

    $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverInfo = $stmt->get_result()->fetch_assoc();
    $srv_remark = $serverInfo['remark'];
    $stmt->close();
    
    $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverConfig = $stmt->get_result()->fetch_assoc();
    $serverType = $serverConfig['type'];
    $portType = $serverConfig['port_type'];
    $panelUrl = $serverConfig['panel_url'];
    $stmt->close();

    // $rnd = rand(1111,99999);
    // $remark = "{$srv_remark}-{$from_id}-{$rnd}";
    $remark = $payInfo['description']; 
    
    if($portType == "auto"){
        file_put_contents('settings/temp.txt',$port.'-'.$last_num);
    }else{
        $port = rand(1111,65000);
    }
    
    if($inbound_id == 0){    
        if($serverType == "marzban"){
            $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
            if(!$response->success){
                if(isset($response->msg) && $response->msg == "User already exists"){
                    $remark .= rand(1111,99999);
                    $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                }
            }
        }else{
            $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid); 
            if(!$response->success){
                if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();
                elseif(isset($response->msg) && strstr($response->msg, "Port already exists")) $port = rand(1111,65000);
                
                $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid);
            }
        }
    }else {
        $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid); 
        if(!$response->success){
            if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();

            $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid);
        } 
    }
    
    if(is_null($response)){
        alert('❌ | 🥺 گلم ، اتصال به سرور برقرار نیست لطفا مدیر رو در جریان بزار ...');
        exit;
    }
	if(isset($response->msg) && $response->msg == "inbound not Found"){
        alert("❌ | 🥺 سطر (inbound) با آیدی $inbound_id تو این سرور وجود نداره ، مدیر رو در جریان بزار ...");
		exit;
	}
	if(!$response->success){
        alert('❌ | 😮 وای خطا داد لطفا سریع به مدیر بگو ...');
        sendMessage("خطای سرور {$serverInfo['title']}:\n\n" . (isset($response->msg)?$response->msg:"Null response"), null, null, $admin);
        exit;
    }
    alert($mainValues['sending_config_to_user']);
    
    $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` - ? WHERE `userid` = ?");
    $stmt->bind_param("ii", $price, $uid);
    $stmt->execute();
    include 'phpqrcode/qrlib.php';
    
    if($serverType == "marzban"){
        $uniqid = $token = str_replace("/sub/", "", $response->obj->subscription_url);
        $subLink = $botState['subLinkState'] == "on"?$panelUrl . $response->obj->subscription_url:"";
        $vraylink = $response->obj->links;
        $vray_link = json_encode($vraylink);
    }
    else{
        $token = RandomString(30);
        $subLink = $botState['subLinkState']=="on"?$botUrl . "settings/subLink.php?token=" . $token:"";
    
        $vraylink = getConnectionLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id, $rahgozar, $customPath, $customPort, $customSni);
        $vray_link = json_encode($vraylink);
    }
    delMessage();
    define('IMAGE_WIDTH',540);
    define('IMAGE_HEIGHT',540);
    foreach($vraylink as $link){
        $acc_text = "
😍 سفارش جدید شما
📡 پروتکل: $protocol
🔮 نام سرویس: $remark
🔋حجم سرویس: $volume گیگ
⏰ مدت سرویس: $days روز⁮⁮ ⁮⁮
" . ($botState['configLinkState'] != "off" && $serverType != "marzban"?"
💝 config : <code>$link</code>":"");
if($botState['subLinkState'] == "on") $acc_text .= "

🔋 Volume web: <code> $botUrl"."search.php?id=".$uniqid."</code>


🌐 subscription : <code>$subLink</code>"; 
    
        $file = RandomString() .".png";
        $ecc = 'L';
        $pixel_Size = 11;
        $frame_Size = 0;
        
        QRcode::png($link, $file, $ecc, $pixel_Size, $frame_Size);
    	addBorderImage($file);
    	
        $backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
        $qrImage = imagecreatefrompng($file);
        
        $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
        imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
        imagepng($backgroundImage, $file);
        imagedestroy($backgroundImage);
        imagedestroy($qrImage);

    	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
        unlink($file);
    }

    
    if($userInfo['refered_by'] != null){
        $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
        $stmt->execute();
        $inviteAmount = $stmt->get_result()->fetch_assoc()['value']??0;
        $stmt->close();
        $inviterId = $userInfo['refered_by'];
        
        $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
        $stmt->bind_param("ii", $inviteAmount, $inviterId);
        $stmt->execute();
        $stmt->close();
         
        sendMessage("تبریک یکی از زیر مجموعه های شما خرید انجام داد شما مبلغ " . number_format($inviteAmount) . " تومان جایزه دریافت کردید",null,null,$inviterId);
    }
    
    $agentBought = $payInfo['agent_bought'];
	$stmt = $connection->prepare("INSERT INTO `orders_list` 
	    (`userid`, `token`, `transid`, `fileid`, `server_id`, `inbound_id`, `remark`, `uuid`, `protocol`, `expire_date`, `link`, `amount`, `status`, `date`, `notif`, `rahgozar`, `agent_bought`)
	    VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?,1, ?, 0, ?, ?);");
    $stmt->bind_param("ssiiisssisiiii", $uid, $token, $fid, $server_id, $inbound_id, $remark, $uniqid, $protocol, $expire_date, $vray_link, $price, $date, $rahgozar, $agentBought);
    $stmt->execute();
    $order = $stmt->get_result(); 
    $stmt->close();
    
    if($inbound_id == 0) {
        $stmt = $connection->prepare("UPDATE `server_info` SET `ucount` = `ucount` - 1 WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $stmt->close();
    }else{
        $stmt = $connection->prepare("UPDATE `server_plans` SET `acount` = `acount` - 1 WHERE id=?");
        $stmt->bind_param("i", $fid);
        $stmt->execute();
        $stmt->close();
    }

    $keys = json_encode(['inline_keyboard'=>[
        [
            ['text'=>"بنازم خرید جدید ❤️",'callback_data'=>"wizwizch"]
        ],
        ]]);
    $msg = str_replace(['TYPE', 'USER-ID', 'USERNAME', 'NAME', 'PRICE', 'REMARK', 'VOLUME', 'DAYS'],
                ['کیف پول', $from_id, $username, $first_name, $price, $remark,$volume, $days], $mainValues['buy_custom_account_request']);
    sendMessage($msg,$keys,"html", $admin);
}
if(preg_match('/^showQr(Sub|Config)(\d+)/',$data,$match)){
    $stmt = $connection->prepare("SELECT * FROM `orders_list` WHERE `userid`=? AND `id`=?");
    $stmt->bind_param("ii", $from_id, $match[2]);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    include 'phpqrcode/qrlib.php';
    define('IMAGE_WIDTH',540);
    define('IMAGE_HEIGHT',540);
    if($match[1] == "Sub"){
        $subLink = $botUrl . "settings/subLink.php?token=" . $order['token'];
        $file = RandomString() .".png";
        $ecc = 'L';
        $pixel_Size = 11;
        $frame_Size = 0;
        
        QRcode::png($subLink, $file, $ecc, $pixel_Size, $frame_Size);
    	addBorderImage($file);
    	
    	$backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
        $qrImage = imagecreatefrompng($file);
        
        $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
        imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
        imagepng($backgroundImage, $file);
        imagedestroy($backgroundImage);
        imagedestroy($qrImage);

    	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
        unlink($file);
    }
    elseif($match[1] == "Config"){

        
        
        $vraylink = json_decode($order['link'],true);
        define('IMAGE_WIDTH',540);
        define('IMAGE_HEIGHT',540);
        foreach($vraylink as $vray_link){
            $file = RandomString() .".png";
            $ecc = 'L';
            $pixel_Size = 11;
            $frame_Size = 0;
            
            QRcode::png($vray_link, $file, $ecc, $pixel_Size, $frame_Size);
        	addBorderImage($file);
            	
        	$backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
            $qrImage = imagecreatefrompng($file);
            
            $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
            imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
            imagepng($backgroundImage, $file);
            imagedestroy($backgroundImage);
            imagedestroy($qrImage);
            
        	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
            unlink($file);
        }
    }
}
if(preg_match('/payCustomWithCartToCart(.*)/',$data, $match)) {
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();

    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    $payInfo = $payInfo->fetch_assoc();
    
    $fid = $payInfo['plan_id'];
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $server_id = $file_detail['server_id'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];


    if($acount == 0 and $inbound_id != 0){
        alert($mainValues['out_of_connection_capacity']);
        exit;
    }
    if($inbound_id == 0) {
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($server_info['ucount'] <= 0) {
            alert($mainValues['out_of_server_capacity']);
            exit;
        }
    }else{
        if($acount != 0 && $acount <= 0){
            sendMessage(str_replace("AMOUNT", $acount, $mainValues['can_create_specific_account']));
            exit();
        }
    }
    
    setUser($data);
    delMessage();
    sendMessage(str_replace(["ACCOUNT-NUMBER", "HOLDER-NAME"],[$paymentKeys['bankAccount'],$paymentKeys['holderName']], $mainValues['buy_account_cart_to_cart']),$cancelKey, "HTML");
    exit;
}
if(preg_match('/payCustomWithCartToCart(.*)/',$userInfo['step'], $match) and $text != $buttonValues['cancel']){
    if(isset($update->message->photo)){
        $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
        $stmt->bind_param("s", $match[1]);
        $stmt->execute();
        $payInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $fid = $payInfo['plan_id'];
        $volume = $payInfo['volume'];
        $days = $payInfo['day'];
        
        setUser();
        $uid = $userInfo['userid'];
        $name = $userInfo['name'];
        $username = $userInfo['username'];
    
        $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
        $stmt->bind_param("i", $fid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    
        $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `id`=?");
        $stmt->bind_param("i", $res['catid']);
        $stmt->execute();
        $catname = $stmt->get_result()->fetch_assoc()['title'];
        $stmt->close();
        $filename = $catname." ".$res['title']; 
        $fileprice = $payInfo['price'];
        $remark = $payInfo['description'];
        
        sendMessage($mainValues['order_buy_sent'],$removeKeyboard);
        sendMessage($mainValues['reached_main_menu'],getMainKeys());
    
        $msg = str_replace(['TYPE', 'USER-ID', 'USERNAME', 'NAME', 'PRICE', 'REMARK', 'VOLUME', 'DAYS'],
                            ["کارت به کارت", $from_id, $username, $first_name, $fileprice, $remark,$volume, $days], $mainValues['buy_custom_account_request']);
        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $buttonValues['approve'], 'callback_data' => "accCustom" . $match[1]],
                    ['text' => $buttonValues['decline'], 'callback_data' => "decline$uid"]
                ]
            ]
        ]);
        $res = sendPhoto($fileid, $msg,$keyboard, "HTML", $admin);
        $msgId = $res->result->message_id;
        
        $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'have_sent', `message_id` = ?, `chat_id` = ? WHERE `hash_id` = ?");
        $stmt->bind_param("iis", $msgId, $admin, $match[1]);
        $stmt->execute();
        $stmt->execute();
    }else{
        sendMessage($mainValues['please_send_only_image']);
    }
}
if(preg_match('/accCustom(.*)/',$data, $match) and $text != $buttonValues['cancel']){
    setUser();

    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if($payInfo['state'] == "approved") exit();

    $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'approved' WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $stmt->close();
    
    $fid = $payInfo['plan_id'];
    $volume = $payInfo['volume'];
    $days = $payInfo['day'];
    $uid = $payInfo['user_id'];

    $acctxt = '';
    
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $date = time();
    $expire_microdate = floor(microtime(true) * 1000) + (864000 * $days * 100);
    $expire_date = $date + (86400 * $days);
    $type = $file_detail['type'];
    $protocol = $file_detail['protocol'];
    $price = $payInfo['price'];
    $server_id = $file_detail['server_id'];
    $netType = $file_detail['type'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];
    $limitip = $file_detail['limitip'];
    $rahgozar = $file_detail['rahgozar'];

    if($acount == 0 and $inbound_id != 0){
        alert($mainValues['out_of_connection_capacity']);
        exit;
    }
    if($inbound_id == 0) {
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $server_info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if($server_info['ucount'] <= 0) {
            alert($mainValues['out_of_server_capacity']);
            exit;
        }
    }

    $uniqid = generateRandomString(42,$protocol); 

    $savedinfo = file_get_contents('settings/temp.txt');
    $savedinfo = explode('-',$savedinfo);
    $port = $savedinfo[0] + 1;
    $last_num = $savedinfo[1] + 1;

    $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverInfo = $stmt->get_result()->fetch_assoc();
    $srv_remark = $serverInfo['remark'];
    $stmt->close();

    $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE `id`=?");
    $stmt->bind_param("i", $server_id);
    $stmt->execute();
    $serverConfig = $stmt->get_result()->fetch_assoc();
    $serverType = $serverConfig['type'];
    $portType = $serverConfig['port_type'];
    $panelUrl = $serverConfig['panel_url'];
    $stmt->close();

    // $rnd = rand(1111,99999);
    // $remark = "{$srv_remark}-{$uid}-{$rnd}";
    $remark = $payInfo['description'];
    
    if($portType == "auto"){
        file_put_contents('settings/temp.txt',$port.'-'.$last_num);
    }else{
        $port = rand(1111,65000);
    }
    
    if($inbound_id == 0){    
        if($serverType == "marzban"){
            $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
            if(!$response->success){
                if(isset($response->msg) && $response->msg == "User already exists"){
                    $remark .= rand(1111,99999);
                    $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                }
            }
        }else{
            $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid); 
            if(!$response->success){
                if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();
                elseif(isset($response->msg) && strstr($response->msg, "Port already exists")) $port = rand(1111,65000);

                $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid);
            }
        }
    }else {
        $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid); 
        if(!$response->success){
            if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();

            $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid);
        } 
    }
    
    if(is_null($response)){
        alert('❌ | 🥺 گلم ، اتصال به سرور برقرار نیست لطفا مدیر رو در جریان بزار ...');
        exit;
    }
	if(isset($response->msg) && $response->msg == "inbound not Found"){
        alert("❌ | 🥺 سطر (inbound) با آیدی $inbound_id تو این سرور وجود نداره ، مدیر رو در جریان بزار ...");
		exit;
	}
	if(!$response->success){
        alert('❌ | 😮 وای خطا داد لطفا سریع به مدیر بگو ...');
        sendMessage("خطای سرور {$serverInfo['title']}:\n\n" . (isset($response->msg)?$response->msg:"Null response"), null, null, $admin);
        exit;
    }
    alert($mainValues['sending_config_to_user']);
    
    include 'phpqrcode/qrlib.php';
    
    if($serverType == "marzban"){
        $uniqid = $token = str_replace("/sub/", "", $response->obj->subscription_url);
        $subLink = $botState['subLinkState'] == "on"?$panelUrl .$response->obj->subscription_url:"";
        $vraylink = $response->obj->links;
        $vray_link= json_encode($vraylink);
    }
    else{
        $token = RandomString(30);
        $subLink = $botState['subLinkState']=="on"?$botUrl . "settings/subLink.php?token=" . $token:"";
    
        $vraylink = getConnectionLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id);
        $vray_link= json_encode($vraylink);
    }
    define('IMAGE_WIDTH',540);
    define('IMAGE_HEIGHT',540);

    foreach($vraylink as $vray_link){
        $acc_text = "
😍 سفارش جدید شما
📡 پروتکل: $protocol
🔮 نام سرویس: $remark
🔋حجم سرویس: $volume گیگ
⏰ مدت سرویس: $days روز⁮⁮ ⁮⁮
" . ($botState['configLinkState'] != "off" && $serverType != "marzban"?"
💝 config : <code>$vray_link</code>":"");
if($botState['subLinkState'] == "on") $acc_text .= "

🔋 Volume web: <code> $botUrl"."search.php?id=".$uniqid."</code>

\n🌐 subscription : <code>$subLink</code>";
    
        $file = RandomString() .".png";
        $ecc = 'L';
        $pixel_Size = 11;
        $frame_Size = 0;
    
        QRcode::png($vray_link, $file, $ecc, $pixel_Size, $frame_Size);
    	addBorderImage($file);
    	
    	$backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
        $qrImage = imagecreatefrompng($file);
        
        $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
        imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
        imagepng($backgroundImage, $file);
        imagedestroy($backgroundImage);
        imagedestroy($qrImage);

    	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
        unlink($file);
    }
    sendMessage('✅ کانفیگ و براش ارسال کردم', getMainKeys());
    
    $agentBought = $payInfo['agent_bought'];
	$stmt = $connection->prepare("INSERT INTO `orders_list` 
	    (`userid`, `token`, `transid`, `fileid`, `server_id`, `inbound_id`, `remark`, `uuid`, `protocol`, `expire_date`, `link`, `amount`, `status`, `date`, `notif`, `rahgozar`, `agent_bought`)
	    VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?,1, ?, 0, ?, ?);");
    $stmt->bind_param("ssiiisssisiiii", $uid, $token, $fid, $server_id, $inbound_id, $remark, $uniqid, $protocol, $expire_date, $vray_link, $price, $date, $rahgozar, $agentBought);
    $stmt->execute();
    $order = $stmt->get_result();
    $stmt->close();


    unset($markup[count($markup)-1]);
    $markup[] = [['text'=>"✅",'callback_data'=>"wizwizch"]];
    $keys = json_encode(['inline_keyboard'=>array_values($markup)],488);


    editKeys($keys);
    
    $filename = $file_detail['title'];
    $fileprice = number_format($file_detail['price']);
    $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid`=?");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $user_detail= $stmt->get_result()->fetch_assoc();
    $stmt->close();


    if($user_detail['refered_by'] != null){
        $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
        $stmt->execute();
        $inviteAmount = $stmt->get_result()->fetch_assoc()['value']??0;
        $stmt->close();
        $inviterId = $user_detail['refered_by'];
        
        $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
        $stmt->bind_param("ii", $inviteAmount, $inviterId);
        $stmt->execute();
        $stmt->close();
         
        sendMessage("تبریک یکی از زیر مجموعه های شما خرید انجام داد شما مبلغ " . number_format($inviteAmount) . " تومان جایزه دریافت کردید",null,null,$inviterId);
    }

    if($inbound_id == 0) {
        $stmt = $connection->prepare("UPDATE `server_info` SET `ucount` = `ucount` - 1 WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $stmt->close();
    }else{
        $stmt = $connection->prepare("UPDATE `server_plans` SET `acount` = `acount` - 1 WHERE id=?");
        $stmt->bind_param("i", $fid);
        $stmt->execute();
        $stmt->close();
    }

    $uname = $user_detail['name'];
    $user_name = $user_detail['username'];
    
    if($admin != $from_id){ 
        $keys = json_encode(['inline_keyboard'=>[
            [
                ['text'=>"به به 🛍",'callback_data'=>"wizwizch"]
            ],
            ]]);
        $msg = str_replace(['USER-ID', 'USERNAME', 'NAME', 'PRICE', 'REMARK', 'FILENAME'],
            [$uid, $user_name, $uname, $price, $remark,$filename], $mainValues['invite_buy_new_account']);
        sendMessage($msg,null,null,$admin);
    }
    
}
if(preg_match('/payWithWallet(.*)/',$data, $match)){
    setUser();

    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();

    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    $payInfo = $payInfo->fetch_assoc();
    
    $uid = $from_id;
    $fid = $payInfo['plan_id'];
    $acctxt = '';
    
    if($payInfo['state'] == "paid_with_wallet") exit();
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $days = $file_detail['days'];
    $date = time();
    $expire_microdate = floor(microtime(true) * 1000) + (864000 * $days * 100);
    $expire_date = $date + (86400 * $days);
    $type = $file_detail['type'];
    $volume = $file_detail['volume'];
    $protocol = $file_detail['protocol'];
    $rahgozar = $file_detail['rahgozar'];
    $customPath = $file_detail['custom_path'];
    $price = $payInfo['price'];
    $customPort = $file_detail['custom_port'];
    $customSni = $file_detail['custom_sni'];
    
    if($userInfo['wallet'] < $price){
        alert("موجودی حساب شما کم است");
        exit();
    }

    $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'paid_with_wallet' WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $stmt->close();

    
    
    $server_id = $file_detail['server_id'];
    $netType = $file_detail['type'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];
    $limitip = $file_detail['limitip'];


    if($payInfo['type'] == "RENEW_SCONFIG"){
        $configInfo = json_decode($payInfo['description'],true);
        $uuid = $configInfo['uuid'];
        $remark = $configInfo['remark'];
        $isMarzban = $configInfo['marzban'];
        
        $inbound_id = $payInfo['volume']; 
        
        if($isMarzban){
            $response = editMarzbanConfig($server_id, ['remark'=>$remark, 'days'=>$days, 'volume' => $volume]);
        }else{
            if($inbound_id > 0)
                $response = editClientTraffic($server_id, $inbound_id, $uuid, $volume, $days, "renew");
            else
                $response = editInboundTraffic($server_id, $uuid, $volume, $days, "renew");
        }
        
    	if(is_null($response)){
    		alert('🔻مشکل فنی در اتصال به سرور. لطفا به مدیریت اطلاع بدید',true);
    		exit;
    	}
    	$stmt = $connection->prepare("INSERT INTO `increase_order` VALUES (NULL, ?, ?, ?, ?, ?, ?);");
    	$stmt->bind_param("iiisii", $uid, $server_id, $inbound_id, $remark, $price, $time);
    	$stmt->execute();
    	$stmt->close();
        $keys = json_encode(['inline_keyboard'=>[
            [
                ['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]
            ],
            ]]);
        editText($message_id,"✅سرویس $remark با موفقیت تمدید شد",$keys);
    }else{
        $accountCount = $payInfo['agent_count']!=0?$payInfo['agent_count']:1;
        
        if($acount == 0 and $inbound_id != 0){
            alert($mainValues['out_of_connection_capacity']);
            exit;
        }
        if($inbound_id == 0) {
            $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
            $stmt->bind_param("i", $server_id);
            $stmt->execute();
            $server_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
    
            if($server_info['ucount'] <= 0) {
                alert($mainValues['out_of_server_capacity']);
                exit;
            }
        }        
    
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $serverInfo = $stmt->get_result()->fetch_assoc();
        $srv_remark = $serverInfo['remark'];
        $serverTitle = $serverInfo['title'];
        $stmt->close();
    
        $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $serverConfig = $stmt->get_result()->fetch_assoc();
        $portType = $serverConfig['port_type'];
        $serverType = $serverConfig['type'];
        $panelUrl = $serverConfig['panel_url'];
        $stmt->close();

        include 'phpqrcode/qrlib.php';
        $msg = $message_id;

        $agent_bought = $payInfo['agent_bought'];
	    $eachPrice = $price / $accountCount;

        alert($mainValues['sending_config_to_user']);
        define('IMAGE_WIDTH',540);
        define('IMAGE_HEIGHT',540);
        for($i = 1; $i <= $accountCount; $i++){
            $uniqid = generateRandomString(42,$protocol); 
        
            $savedinfo = file_get_contents('settings/temp.txt');
            $savedinfo = explode('-',$savedinfo);
            $port = $savedinfo[0] + 1;
            $last_num = $savedinfo[1] + 1;
        
        
            if($botState['remark'] == "digits"){
                $rnd = rand(10000,99999);
                $remark = "{$srv_remark}-{$rnd}";
            }
            elseif($botState['remark'] == "manual"){
                $remark = $payInfo['description'];
            }
            else{
                $rnd = rand(1111,99999);
                $remark = "{$srv_remark}-{$from_id}-{$rnd}";
            }
        
            if($portType == "auto"){
                file_put_contents('settings/temp.txt',$port.'-'.$last_num);
            }else{
                $port = rand(1111,65000);
            }
        
            if($inbound_id == 0){    
                if($serverType == "marzban"){
                    $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                    if(!$response->success){
                        if(isset($response->msg) && $response->msg == "User already exists"){
                            $remark .= rand(1111,99999);
                            $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                        }
                    }
                }
                else{
                    $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid); 
                    if(!$response->success){
                        if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();
                        elseif(isset($response->msg) && strstr($response->msg, "Port already exists")) $port = rand(1111,65000);

                        $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid);
                    }
                }
            }else {
                $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid); 
                if(!$response->success){
                    if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();

                    $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid);
                } 
            }
            if(is_null($response)){
                sendMessage('❌ | 🥺 گلم ، اتصال به سرور برقرار نیست لطفا مدیر رو در جریان بزار ...');
                exit;
            }
		if(isset($response->msg) && $response->msg == "inbound not Found"){
                sendMessage("❌ | 🥺 سطر (inbound) با آیدی $inbound_id تو این سرور وجود نداره ، مدیر رو در جریان بزار ...");
        		exit;
        	}
        	if(!$response->success){
                sendMessage('❌ | 😮 وای خطا داد لطفا سریع به مدیر بگو ...');
                sendMessage("خطای سرور {$serverInfo['title']}:\n\n" . (isset($response->msg)?$response->msg:"Null response"), null, null, $admin);
                exit;
            }
        
        
            if($serverType == "marzban"){
                $uniqid = $token = str_replace("/sub/", "", $response->obj->subscription_url);
                $subLink = $botState['subLinkState'] == "on"?$panelUrl . $response->obj->subscription_url:"";
                $vraylink = $response->obj->links;
                $vray_link= json_encode($vraylink);
            }
            else{
                $token = RandomString(30);
                $vraylink = getConnectionLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id, $rahgozar, $customPath, $customPort, $customSni);
                $vray_link= json_encode($vraylink);
                $subLink = $botState['subLinkState']=="on"?$botUrl . "settings/subLink.php?token=" . $token:"";
            }

            foreach($vraylink as $link){
                $acc_text = "
😍 سفارش جدید شما
📡 پروتکل: $protocol
🔮 نام سرویس: $remark
🔋حجم سرویس: $volume گیگ
⏰ مدت سرویس: $days روز⁮⁮ ⁮⁮
" . ($botState['configLinkState'] != "off" && $serverType != "marzban"?"
💝 config : <code>$link</code>":"");
if($botState['subLinkState'] == "on") $acc_text .= "

🔋 Volume web: <code> $botUrl"."search.php?id=".$uniqid."</code>

\n🌐 subscription : <code>$subLink</code>";
            
                $file = RandomString() .".png";
                $ecc = 'L';
                $pixel_Size = 11;
                $frame_Size = 0;
                
                QRcode::png($link, $file, $ecc, $pixel_Size, $frame_Size);
            	addBorderImage($file);
            	
	        	$backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
                $qrImage = imagecreatefrompng($file);
                
                $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
                imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
                imagepng($backgroundImage, $file);
                imagedestroy($backgroundImage);
                imagedestroy($qrImage);

            	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
                unlink($file);
            }
            
        	$stmt = $connection->prepare("INSERT INTO `orders_list` 
        	    (`userid`, `token`, `transid`, `fileid`, `server_id`, `inbound_id`, `remark`, `uuid`, `protocol`, `expire_date`, `link`, `amount`, `status`, `date`, `notif`, `rahgozar`, `agent_bought`)
        	    VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?,1, ?, 0, ?, ?);");
            $stmt->bind_param("ssiiisssisiiii", $uid, $token, $fid, $server_id, $inbound_id, $remark, $uniqid, $protocol, $expire_date, $vray_link, $eachPrice, $date, $rahgozar, $agent_bought);
            $stmt->execute();
            $order = $stmt->get_result(); 
            $stmt->close();
        }
    
        delMessage($msg);
        if($userInfo['refered_by'] != null){
            $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
            $stmt->execute();
            $inviteAmount = $stmt->get_result()->fetch_assoc()['value']??0;
            $stmt->close();
            $inviterId = $userInfo['refered_by'];
            
            $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
            $stmt->bind_param("ii", $inviteAmount, $inviterId);
            $stmt->execute();
            $stmt->close();
             
            sendMessage("تبریک یکی از زیر مجموعه های شما خرید انجام داد شما مبلغ " . number_format($inviteAmount) . " تومان جایزه دریافت کردید",null,null,$inviterId);
        }
        if($inbound_id == 0) {
            $stmt = $connection->prepare("UPDATE `server_info` SET `ucount` = `ucount` - ? WHERE `id`=?");
            $stmt->bind_param("ii", $accountCount, $server_id);
            $stmt->execute();
            $stmt->close();
        }else{
            $stmt = $connection->prepare("UPDATE `server_plans` SET `acount` = `acount` - ? WHERE id=?");
            $stmt->bind_param("ii", $accountCount, $fid);
            $stmt->execute();
            $stmt->close();
        }
    }
    $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` - ? WHERE `userid` = ?");
    $stmt->bind_param("ii", $price, $uid);
    $stmt->execute();
    $stmt->close();
    
    $keys = json_encode(['inline_keyboard'=>[
        [
            ['text'=>"بنازم خرید جدید ❤️",'callback_data'=>"wizwizch"]
        ],
        ]]);
    if($payInfo['type'] == "RENEW_SCONFIG"){$msg = str_replace(['TYPE', 'USER-ID', 'USERNAME', 'NAME', 'PRICE', 'REMARK', 'VOLUME', 'DAYS'],
                ['کیف پول', $from_id, $username, $first_name, $price, $remark,$volume, $days], $mainValues['renew_account_request_message']);}
    else{$msg = str_replace(['SERVERNAME', 'TYPE', 'USER-ID', 'USERNAME', 'NAME', 'PRICE', 'REMARK', 'VOLUME', 'DAYS'],
                [$serverTitle, 'کیف پول', $from_id, $username, $first_name, $price, $remark,$volume, $days], $mainValues['buy_new_account_request']);}

    sendMessage($msg,$keys,"html", $admin);
}
if(preg_match('/payWithCartToCart(.*)/',$data,$match)) {
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result();
    $stmt->close();

    if($payInfo->num_rows == 0){
        $text = "/start";
        $data = "";
        delMessage();
        goto GOTOSTART;
    }
    $payInfo = $payInfo->fetch_assoc();
    
    $fid = $payInfo['plan_id'];
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    $server_id = $file_detail['server_id'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];

    if($payInfo['type'] != "RENEW_SCONFIG"){
        if($acount == 0 and $inbound_id != 0){
            alert($mainValues['out_of_connection_capacity']);
            exit;
        }
        if($inbound_id == 0) {
            $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
            $stmt->bind_param("i", $server_id);
            $stmt->execute();
            $server_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
    
            if($server_info['ucount'] <= 0) {
                alert($mainValues['out_of_server_capacity']);
                exit;
            }
        }else{
            if($acount <= 0){
                alert(str_replace("AMOUNT", $acount, $mainValues['can_create_specific_account']));
                exit();
            }
        }
    }
    
    setUser($data);
    delMessage();
    sendMessage(str_replace(["ACCOUNT-NUMBER", "HOLDER-NAME"],[$paymentKeys['bankAccount'],$paymentKeys['holderName']], $mainValues['buy_account_cart_to_cart']),$cancelKey, "HTML");
    exit;
}
if(preg_match('/payWithCartToCart(.*)/',$userInfo['step'], $match) and $text != $buttonValues['cancel']){
    if(isset($update->message->photo)){
        $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
        $stmt->bind_param("s", $match[1]);
        $stmt->execute();
        $payInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        
        $fid = $payInfo['plan_id'];
        setUser();
        $uid = $userInfo['userid'];
        $name = $userInfo['name'];
        $username = $userInfo['username'];
    
        $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
        $stmt->bind_param("i", $fid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $days = $res['days'];
        $volume = $res['volume'];
        
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $res['server_id']);
        $stmt->execute();
        $serverInfo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $serverTitle = $serverInfo['title'];
    
        if($payInfo['type'] == "RENEW_SCONFIG"){
            $configInfo = json_decode($payInfo['description'],true);
            $filename = $configInfo['remark'];
        }else{
            $stmt = $connection->prepare("SELECT * FROM `server_categories` WHERE `id`=?");
            $stmt->bind_param("i", $res['catid']);
            $stmt->execute();
            $catname = $stmt->get_result()->fetch_assoc()['title'];
            $stmt->close();
            $filename = $catname." ".$res['title']; 
        }
        $fileprice = $payInfo['price'];
    
        sendMessage($mainValues['order_buy_sent'],$removeKeyboard);
        sendMessage($mainValues['reached_main_menu'],getMainKeys());
    
        if($payInfo['agent_count'] != 0) $msg = str_replace(['ACCOUNT-COUNT', 'TYPE', 'USER-ID', "USERNAME", "NAME", "PRICE", "REMARK"],[$payInfo['agent_count'], 'کارت به کارت', $from_id, $username, $name, $fileprice, $filename], $mainValues['buy_new_much_account_request']);
        else $msg = str_replace(['SERVERNAME', 'TYPE', 'USER-ID', "USERNAME", "NAME", "PRICE", "REMARK", "VOLUME", "DAYS"],[$serverTitle, 'کارت به کارت', $from_id, $username, $name, $fileprice, $filename, $volume, $days], $mainValues['buy_new_account_request']);

        $keyboard = json_encode([
            'inline_keyboard' => [
                [
                    ['text' => $buttonValues['approve'], 'callback_data' => "accept" . $match[1] ],
                    ['text' => $buttonValues['decline'], 'callback_data' => "decline$uid"]
                ]
            ]
        ]);
        setUser('', 'temp');
        $res = sendPhoto($fileid, $msg,$keyboard, "HTML", $admin);
        $msgId = $res->result->message_id;
        
        $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'have_sent', `message_id` = ?, `chat_id` = ? WHERE `hash_id` = ?");
        $stmt->bind_param("iis", $msgId, $admin, $match[1]);
        $stmt->execute();
        $stmt->close();
    }else{
        sendMessage($mainValues['please_send_only_image']);
    }
}
if($data=="availableServers"){
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `acount` != 0 AND `inbound_id` != 0");
    $stmt->execute();
    $serversList = $stmt->get_result();
    $stmt->close();

    $keys = array();
    $keys[] = [
        ['text'=>"تعداد باقیمانده",'callback_data'=>"wizwizch"],
        ['text'=>"پلن",'callback_data'=>"wizwizch"],
        ['text'=>'سرور','callback_data'=>"wizwizch"]
        ];
    while($file_detail = $serversList->fetch_assoc()){
        $days = $file_detail['days'];
        $title = $file_detail['title'];
        $server_id = $file_detail['server_id'];
        $acount = $file_detail['acount'];
        $inbound_id = $file_detail['inbound_id'];
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id` = ?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $name = $stmt->get_result();
        $stmt->close();

        if($name->num_rows>0){
            $name = $name->fetch_assoc()['title'];
            
            $keys[] = [
                ['text'=>$acount . " اکانت",'callback_data'=>"wizwizch"],
                ['text'=>$title??" ",'callback_data'=>"wizwizch"],
                ['text'=>$name??" ",'callback_data'=>"wizwizch"]
                ];
        }
    }
    $keys[] = [['text'=>$buttonValues['back_button'],'callback_data'=>"mainMenu"]];
    $keys = json_encode(['inline_keyboard'=>$keys]);
    editText($message_id, "🟢 | موجودی پلن اشتراکی:", $keys);
}
if($data=="availableServers2"){
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `inbound_id` = 0");
    $stmt->execute();
    $serversList = $stmt->get_result();
    $stmt->close();

    $keys = array();
    $keys[] = [
        ['text'=>"تعداد باقیمانده",'callback_data'=>"wizwizch"],
        ['text'=>'سرور','callback_data'=>"wizwizch"]
        ];
    while($file_detail2 = $serversList->fetch_assoc()){
        $days2 = $file_detail2['days'];
        $title2 = $file_detail2['title'];
        $server_id2 = $file_detail2['server_id'];
        $inbound_id2 = $file_detail2['inbound_id'];
        
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id` = ?");
        $stmt->bind_param("i", $server_id2);
        $stmt->execute();
        $name = $stmt->get_result();
        $stmt->close();

        if($name->num_rows>0){
            $sInfo = $name->fetch_assoc();
            $name = $sInfo['title'];
            $acount2 = $sInfo['ucount'];
            
            $keys[] = [
                ['text'=>$acount2 . " اکانت",'callback_data'=>"wizwizch"],
                ['text'=>$title2??" ",'callback_data'=>"wizwizch"],
                ];
        }
    }
    $keys[] = [['text'=>$buttonValues['back_button'],'callback_data'=>"mainMenu"]];
    $keys = json_encode(['inline_keyboard'=>$keys]);
    editText($message_id, "🟢 | موجودی پلن اختصاصی:", $keys);
}
if($data=="agencySettings" && $userInfo['is_agent'] == 1){
    editText($message_id, $mainValues['agent_setting_message'] ,getAgentKeys());
}
if($data=="requestAgency"){
    if($userInfo['is_agent'] == 2){
        alert($mainValues['agency_request_already_sent']);
    }elseif($userInfo['is_agent'] == 0){
        $msg = str_replace(["USERNAME", "NAME", "USERID"], [$username, $first_name, $from_id], $mainValues['request_agency_message']);
        sendMessage($msg, json_encode(['inline_keyboard'=>[
            [
                ['text' => $buttonValues['approve'], 'callback_data' => "agencyApprove" . $from_id ],
                ['text' => $buttonValues['decline'], 'callback_data' => "agencyDecline" . $from_id]
            ]
            ]]), null, $admin);
        setUser(2, 'is_agent');
        alert($mainValues['agency_request_sent']);
    }elseif($userInfo['is_agent'] == -1) alert($mainValues['agency_request_declined']);
    elseif($userInfo['is_agent'] == 1) editText($message_id,"لطفا یکی از کلید های زیر را انتخاب کنید",getMainKeys());
}
if(preg_match('/^agencyDecline(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    editKeys(json_encode(['inline_keyboard'=>[
        [['text'=>$buttonValues['declined'],'callback_data'=>"wizwizch"]]
        ]]));
    sendMessage($mainValues['agency_request_declined'], null,null,$match[1]);
    setUser(-1, 'is_agent', $match[1]);
}
if(preg_match('/^agencyApprove(\d+)/',$data,$match) && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data . "_" . $message_id);
    sendMessage($mainValues['send_agent_discount_percent'], $cancelKey);
}
if(preg_match('/^agencyApprove(\d+)_(\d+)/',$userInfo['step'],$match) && $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    if(is_numeric($text)){
        editKeys(json_encode(['inline_keyboard'=>[
            [['text'=>$buttonValues['approved'],'callback_data'=>"wizwizch"]]
            ]]), $match[2]);
        sendMessage($mainValues['saved_successfuly']);
        setUser();
        $discount = json_encode(['normal'=>$text]);
        $stmt = $connection->prepare("UPDATE `users` SET `is_agent` = 1, `discount_percent` = ?, `agent_date` = ? WHERE `userid` = ?");
        $stmt->bind_param("sii", $discount, $time, $match[1]);
        $stmt->execute();
        $stmt->close();
        sendMessage($mainValues['agency_request_approved'], null,null,$match[1]);
    }else sendMessage($mainValues['send_only_number']);
}
if(preg_match('/accept(.*)/',$data, $match) and $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser();
    
    $stmt = $connection->prepare("SELECT * FROM `pays` WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $payInfo = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if($payInfo['state'] == "approved") exit();

    $stmt = $connection->prepare("UPDATE `pays` SET `state` = 'approved' WHERE `hash_id` = ?");
    $stmt->bind_param("s", $match[1]);
    $stmt->execute();
    $stmt->close();

    $uid = $payInfo['user_id'];
    $fid = $payInfo['plan_id'];
    $acctxt = '';
    
    
    $stmt = $connection->prepare("SELECT * FROM `server_plans` WHERE `id`=?");
    $stmt->bind_param("i", $fid);
    $stmt->execute();
    $file_detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $days = $file_detail['days'];
    $date = time();
    $expire_microdate = floor(microtime(true) * 1000) + (864000 * $days * 100);
    $expire_date = $date + (86400 * $days);
    $type = $file_detail['type'];
    $volume = $file_detail['volume'];
    $protocol = $file_detail['protocol'];
    $price = $payInfo['price'];
    $server_id = $file_detail['server_id'];
    $netType = $file_detail['type'];
    $acount = $file_detail['acount'];
    $inbound_id = $file_detail['inbound_id'];
    $limitip = $file_detail['limitip'];
    $rahgozar = $file_detail['rahgozar'];
    $customPath = $file_detail['custom_path'];
    $customPort = $file_detail['custom_port'];
    $customSni = $file_detail['custom_sni'];

    
    if($payInfo['type'] == "RENEW_SCONFIG"){
        $configInfo = json_decode($payInfo['description'],true);
        $uuid = $configInfo['uuid'];
        $remark = $configInfo['remark'];
        $isMarzban = $configInfo['marzban'];
        
        $inbound_id = $payInfo['volume']; 
        
        if($isMarzban){
            $response = editMarzbanConfig($server_id, ['remark'=>$remark, 'days'=>$days, 'volume' => $volume]);
        }else{
            if($inbound_id > 0)
                $response = editClientTraffic($server_id, $inbound_id, $uuid, $volume, $days, "renew");
            else
                $response = editInboundTraffic($server_id, $uuid, $volume, $days, "renew");
        }
        
    	if(is_null($response)){
    		alert('🔻مشکل فنی در اتصال به سرور. لطفا به مدیریت اطلاع بدید',true);
    		exit;
    	}
    	$stmt = $connection->prepare("INSERT INTO `increase_order` VALUES (NULL, ?, ?, ?, ?, ?, ?);");
    	$stmt->bind_param("iiisii", $uid, $server_id, $inbound_id, $remark, $price, $time);
    	$stmt->execute();
    	$stmt->close();
        sendMessage(str_replace(["REMARK", "VOLUME", "DAYS"],[$remark, $volume, $days], $mainValues['renewed_config_to_user']), getMainKeys(),null,null);
        sendMessage("✅سرویس $remark با موفقیت تمدید شد",null,null,$uid);
    }else{
        $accountCount = $payInfo['agent_count'] != 0? $payInfo['agent_count']:1;
        $eachPrice = $price / $accountCount;
        
        if($acount == 0 and $inbound_id != 0){
            alert($mainValues['out_of_connection_capacity']);
            exit;
        }
        if($inbound_id == 0) {
            $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
            $stmt->bind_param("i", $server_id);
            $stmt->execute();
            $server_info = $stmt->get_result()->fetch_assoc();
            $stmt->close();
    
            if($server_info['ucount'] <= 0){
                alert($mainValues['out_of_server_capacity']);
                exit;
            }
        }
        
        $stmt = $connection->prepare("SELECT * FROM `server_info` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $serverInfo = $stmt->get_result()->fetch_assoc();
        $srv_remark = $serverInfo['remark'];
        $stmt->close();
    
        $stmt = $connection->prepare("SELECT * FROM `server_config` WHERE `id`=?");
        $stmt->bind_param("i", $server_id);
        $stmt->execute();
        $serverConfig = $stmt->get_result()->fetch_assoc();
        $serverType = $serverConfig['type'];
        $portType = $serverConfig['port_type'];
        $panelUrl = $serverConfig['panel_url'];
        $stmt->close();
    
    
        alert($mainValues['sending_config_to_user']);
        include 'phpqrcode/qrlib.php';
        define('IMAGE_WIDTH',540);
        define('IMAGE_HEIGHT',540);
        for($i = 1; $i <= $accountCount; $i++){
            $uniqid = generateRandomString(42,$protocol); 
        
            $savedinfo = file_get_contents('settings/temp.txt');
            $savedinfo = explode('-',$savedinfo);
            $port = $savedinfo[0] + 1;
            $last_num = $savedinfo[1] + 1;
    
    
            if($botState['remark'] == "digits"){
                $rnd = rand(10000,99999);
                $remark = "{$srv_remark}-{$rnd}";
            }
            elseif($botState['remark'] == "manual"){
                $remark = $payInfo['description'];
            }
            else{
                $rnd = rand(1111,99999);
                $remark = "{$srv_remark}-{$uid}-{$rnd}";
            }
        
            if($portType == "auto"){
                file_put_contents('settings/temp.txt',$port.'-'.$last_num);
            }else{
                $port = rand(1111,65000);
            }
        
            if($inbound_id == 0){   
                if($serverType == "marzban"){
                    $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                    if(!$response->success){
                        if(isset($response->msg) && $response->msg == "User already exists"){
                            $remark .= rand(1111,99999);
                            $response = addMarzbanUser($server_id, $remark, $volume, $days, $fid);
                        }
                    }
                }
                else{
                    $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid); 
                    if(!$response->success){
                        if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();
                        elseif(isset($response->msg) && strstr($response->msg, "Port already exists")) $port = rand(1111,65000);

                        $response = addUser($server_id, $uniqid, $protocol, $port, $expire_microdate, $remark, $volume, $netType, 'none', $rahgozar, $fid);
                    }
                }
            }else {
                $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid); 
                if(!$response->success){
                    if(isset($response->msg) && strstr($response->msg, "Duplicate email")) $remark .= RandomString();

                    $response = addInboundAccount($server_id, $uniqid, $inbound_id, $expire_microdate, $remark, $volume, $limitip, null, $fid);
                } 
            }
            if(is_null($response)){
                sendMessage('❌ | 🥺 گلم ، اتصال به سرور برقرار نیست لطفا مدیر رو در جریان بزار ...');
                exit;
            }
		if(isset($response->msg) && $response->msg == "inbound not Found"){
                sendMessage("❌ | 🥺 سطر (inbound) با آیدی $inbound_id تو این سرور وجود نداره ، مدیر رو در جریان بزار ...");
        		exit;
        	}
        	if(!$response->success){
                sendMessage('❌ | 😮 وای خطا داد لطفا سریع به مدیر بگو ...');
                sendMessage("خطای سرور {$serverInfo['title']}:\n\n" . (isset($response->msg)?$response->msg:"Null response"), null, null, $admin);
                exit;
            }
                
            if($serverType == "marzban"){
                $uniqid = $token = str_replace("/sub/", "", $response->obj->subscription_url);
                $subLink = $botState['subLinkState'] == "on"?$panelUrl .$response->obj->subscription_url:"";
                $vraylink = $response->obj->links;
                $vray_link = json_encode($vraylink);
            }
            else{
                $token = RandomString(30);
                $subLink = $botState['subLinkState']=="on"?$botUrl . "settings/subLink.php?token=" . $token:"";
        
                $vraylink = getConnectionLink($server_id, $uniqid, $protocol, $remark, $port, $netType, $inbound_id, $rahgozar, $customPath, $customPort, $customSni);
                $vray_link = json_encode($vraylink);
            }
            foreach($vraylink as $link){
                $acc_text = "
😍 سفارش جدید شما
📡 پروتکل: $protocol
🔮 نام سرویس: $remark
🔋حجم سرویس: $volume گیگ
⏰ مدت سرویس: $days روز
" . ($botState['configLinkState'] != "off" && $serverType != "marzban"?"
💝 config : <code>$link</code>":"");
if($botState['subLinkState'] == "on") $acc_text .= "

🔋 Volume web: <code> $botUrl"."search.php?id=".$uniqid."</code>

\n🌐 subscription : <code>$subLink</code>";
            
                $file = RandomString() .".png";
                $ecc = 'L';
                $pixel_Size = 11;
                $frame_Size = 0;
            
                QRcode::png($link, $file, $ecc, $pixel_Size, $frame_Size);
            	addBorderImage($file);
            	
            	
	        	$backgroundImage = imagecreatefromjpeg("settings/QRCode.jpg");
                $qrImage = imagecreatefrompng($file);
                
                $qrSize = array('width' => imagesx($qrImage), 'height' => imagesy($qrImage));
                imagecopy($backgroundImage, $qrImage, 300, 300 , 0, 0, $qrSize['width'], $qrSize['height']);
                imagepng($backgroundImage, $file);
                imagedestroy($backgroundImage);
                imagedestroy($qrImage);

            	sendPhoto($botUrl . $file, $acc_text,json_encode(['inline_keyboard'=>[[['text'=>$buttonValues['back_to_main'],'callback_data'=>"mainMenu"]]]]),"HTML", $uid);
                unlink($file);
            }
            $agent_bought = $payInfo['agent_bought'];
    
        	$stmt = $connection->prepare("INSERT INTO `orders_list` 
        	    (`userid`, `token`, `transid`, `fileid`, `server_id`, `inbound_id`, `remark`, `uuid`, `protocol`, `expire_date`, `link`, `amount`, `status`, `date`, `notif`, `rahgozar`, `agent_bought`)
        	    VALUES (?, ?, '', ?, ?, ?, ?, ?, ?, ?, ?, ?,1, ?, 0, ?, ?);");
            $stmt->bind_param("ssiiisssisiiii", $uid, $token, $fid, $server_id, $inbound_id, $remark, $uniqid, $protocol, $expire_date, $vray_link, $eachPrice, $date, $rahgozar, $agent_bought);
            $stmt->execute();
            $order = $stmt->get_result();
            $stmt->close();
        }
        sendMessage(str_replace(["REMARK", "VOLUME", "DAYS"],[$remark, $volume, $days], $mainValues['sent_config_to_user']), getMainKeys());
        if($inbound_id == 0) {
            $stmt = $connection->prepare("UPDATE `server_info` SET `ucount` = `ucount` - ? WHERE `id`=?");
            $stmt->bind_param("ii", $accountCount, $server_id);
            $stmt->execute();
            $stmt->close();
        }else{
            $stmt = $connection->prepare("UPDATE `server_plans` SET `acount` = `acount` - ? WHERE id=?");
            $stmt->bind_param("ii", $accountCount, $fid);
            $stmt->execute();
            $stmt->close();
        }

    }

    unset($markup[count($markup)-1]);
    $markup[] = [['text'=>"✅",'callback_data'=>"wizwizch"]];
    $keys = json_encode(['inline_keyboard'=>array_values($markup)],488);

    editKeys($keys);
    if($payInfo['type'] != "RENEW_SCONFIG"){
        $filename = $file_detail['title'];
        $fileprice = number_format($file_detail['price']);
        $stmt = $connection->prepare("SELECT * FROM `users` WHERE `userid`=?");
        $stmt->bind_param("i", $uid);
        $stmt->execute();
        $user_detail= $stmt->get_result()->fetch_assoc();
        $stmt->close();
    
        if($user_detail['refered_by'] != null){
            $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'INVITE_BANNER_AMOUNT'");
            $stmt->execute();
            $inviteAmount = $stmt->get_result()->fetch_assoc()['value']??0;
            $stmt->close();
            $inviterId = $user_detail['refered_by'];
            
            $stmt = $connection->prepare("UPDATE `users` SET `wallet` = `wallet` + ? WHERE `userid` = ?");
            $stmt->bind_param("ii", $inviteAmount, $inviterId);
            $stmt->execute();
            $stmt->close();
             
            sendMessage("تبریک یکی از زیر مجموعه های شما خرید انجام داد شما مبلغ " . number_format($inviteAmount) . " تومان جایزه دریافت کردید",null,null,$inviterId);
        }
    
    
        $uname = $user_detail['name'];
        $user_name = $user_detail['username'];
        
        if($admin != $from_id){
            $keys = json_encode(['inline_keyboard'=>[
                [
                    ['text'=>"به به 🛍",'callback_data'=>"wizwizch"]
                ],
                ]]);
                
        $msg = str_replace(['USER-ID', 'USERNAME', 'NAME', 'PRICE', 'REMARK', 'FILENAME'],
                    [$uid, $user_name, $uname, $price, $remark,$filename], $mainValues['invite_buy_new_account']);
            
            sendMessage($msg,null,null,$admin);
        }
    }
}
if(preg_match('/decline/',$data) and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data . "_" . $message_id);
    sendMessage('دلیلت از عدم تایید چیه؟ ( بفرس براش ) 😔 ',$cancelKey);
}
if(preg_match('/decline(\d+)_(\d+)/',$userInfo['step'],$match) && ($from_id == $admin || $userInfo['isAdmin'] == true) and $text != $buttonValues['cancel']){
    setUser();
    $uid = $match[1];
    editKeys(
        json_encode(['inline_keyboard'=>[
	    [['text'=>"لغو شد ❌",'callback_data'=>"wizwizch"]]
	    ]]) ,$match[2]);

    sendMessage('پیامت رو براش ارسال کردم ... 🤝',$removeKeyboard);
    sendMessage($mainValues['reached_main_menu'],getMainKeys());
    
    sendMessage($text, null, null, $uid);
}
if($data=="supportSection"){
    editText($message_id,"به بخش پشتیبانی خوش اومدی🛂\nلطفا، یکی از دکمه های زیر را انتخاب نمایید.",
        json_encode(['inline_keyboard'=>[
        [['text'=>"✉️ ثبت تیکت",'callback_data'=>"usersNewTicket"]],
        [['text'=>"تیکت های باز 📨",'callback_data'=>"usersOpenTickets"],['text'=>"📮 لیست تیکت ها", 'callback_data'=>"userAllTickets"]],
        [['text'=>$buttonValues['back_button'],'callback_data'=>"mainMenu"]]
        ]]));
}
if($data== "usersNewTicket"){
    $stmt = $connection->prepare("SELECT * FROM `setting` WHERE `type` = 'TICKETS_CATEGORY'");
    $stmt->execute();
    $ticketCategory = $stmt->get_result();
    $stmt->close();
    $keys = array();
    $temp = array();
    if($ticketCategory->num_rows >0){
        while($row = $ticketCategory->fetch_assoc()){
            $ticketName = $row['value'];
            $temp[] = ['text'=>$ticketName,'callback_data'=>"supportCat$ticketName"];
            
            if(count($temp) == 2){
                array_push($keys,$temp);
                $temp = null;
            }
        }
        
        if($temp != null){
            if(count($temp)>0){
                array_push($keys,$temp);
                $temp = null;
            }
        }
        $temp[] = ['text'=>$buttonValues['back_button'],'callback_data'=>"mainMenu"];
        array_push($keys,$temp);
        editText($message_id,"💠لطفا واحد مورد نظر خود را انتخاب نمایید!",json_encode(['inline_keyboard'=>$keys]));
    }else{
        alert("ای وای، ببخشید الان نیستم");
    }
}
if($data == 'dayPlanSettings' and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `increase_day`");
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if($res->num_rows == 0){
       editText($message_id, 'لیست پلن های زمانی خالی است ',json_encode([
                'inline_keyboard' => [
                    [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]],
                    [['text'=>$buttonValues['back_button'],'callback_data'=>"backplan"]]
                ]
            ]));
        exit;
    }
    $keyboard = [];
    $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"تعداد روز",'callback_data'=>"wizwizch"]];
    while($cat = $res->fetch_assoc()){
        $id = $cat['id'];
        $title = $cat['volume'];
        $price=number_format($cat['price']) . " تومان";
        $acount =$cat['acount'];

        $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteDayPlan" . $id],['text'=>$price,'callback_data'=>"changeDayPlanPrice" . $id],['text'=>$title,'callback_data'=>"changeDayPlanDay" . $id]];
    }
    $keyboard[] = [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]];
    $keyboard[] = [['text' => $buttonValues['back_button'], 'callback_data' => "backplan"]];
    $msg = ' 📍 برای دیدن جزییات پلن زمانی روی آن بزنید👇';
    
    editText($message_id,$msg,json_encode([
            'inline_keyboard' => $keyboard
        ]));

    exit;
}
if($data=='addNewDayPlan' and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data);
    delMessage();
    sendMessage("تعداد روز و قیمت آن را بصورت زیر وارد کنید :
10-30000

مقدار اول مدت زمان (10) روز
مقدار دوم قیمت (30000) تومان
 ",$cancelKey);exit;
}
if($userInfo['step'] == "addNewDayPlan" and $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)) {
    $input = explode('-',$text); 
    $volume = $input[0];
    $price = $input[1];
    $stmt = $connection->prepare("INSERT INTO `increase_day` VALUES (NULL, ?, ?)");
    $stmt->bind_param("ii", $volume, $price);
    $stmt->execute();
    $stmt->close();
    
    sendMessage("پلن زمانی جدید با موفقیت اضافه شد",$removeKeyboard);
    sendMessage($mainValues['reached_main_menu'],getAdminKeys());
    setUser();
}
if(preg_match('/^deleteDayPlan(\d+)/',$data,$match) and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("DELETE FROM `increase_day` WHERE `id` = ?");
    $stmt->bind_param("i", $match[1]);
    $stmt->execute();
    $stmt->close();
    alert("پلن موردنظر با موفقیت حذف شد");
    
    
    $stmt = $connection->prepare("SELECT * FROM `increase_day`");
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if($res->num_rows == 0){
       editText($message_id, 'لیست پلن های زمانی خالی است ',json_encode([
                'inline_keyboard' => [
                    [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]],
                    [['text'=>$buttonValues['back_button'],'callback_data'=>"managePanel"]]
                ]
            ]));
        exit;
    }
    $keyboard = [];
    $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"تعداد روز",'callback_data'=>"wizwizch"]];
    while($cat = $res->fetch_assoc()){
        $id = $cat['id'];
        $title = $cat['volume'];
        $price=number_format($cat['price']) . " تومان";
        $acount =$cat['acount'];

        $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteDayPlan" . $id],['text'=>$price,'callback_data'=>"changeDayPlanPrice" . $id],['text'=>$title,'callback_data'=>"changeDayPlanDay" . $id]];
    }
    $keyboard[] = [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]];
    $keyboard[] = [['text' => $buttonValues['back_button'], 'callback_data' => "managePanel"]];
    $msg = ' 📍 برای دیدن جزییات پلن زمانی روی آن بزنید👇';
    
    editText($message_id,$msg,json_encode([
            'inline_keyboard' => $keyboard
        ]));

    exit;
}
if(preg_match('/^changeDayPlanPrice(\d+)/',$data,$match) and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data);
    delMessage();
    sendMessage("قیمت جدید را وارد کنید:", $cancelKey);
    exit;
}
if(preg_match('/^changeDayPlanPrice(\d+)/',$userInfo['step'],$match) and $text != $buttonValues['cancel']){
    if(is_numeric($text)){
        setUser();
        $stmt = $connection->prepare("UPDATE `increase_day` SET `price` = ? WHERE `id` = ?");
        $stmt->bind_param("ii", $text, $match[1]);
        $stmt->execute();
        $stmt->close();
        
        sendMessage("✅عملیات با موفقیت انجام شد",$removeKeyboard);
        
        $stmt = $connection->prepare("SELECT * FROM `increase_day`");
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
    
        if($res->num_rows == 0){
           sendMessage( 'لیست پلن های زمانی خالی است ',json_encode([
                    'inline_keyboard' => [
                        [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]],
                        [['text'=>$buttonValues['back_button'],'callback_data'=>"managePanel"]]
                    ]
                ]));
            exit;
        }
        $keyboard = [];
        $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"تعداد روز",'callback_data'=>"wizwizch"]];
        while($cat = $res->fetch_assoc()){
            $id = $cat['id'];
            $title = $cat['volume'];
            $price=number_format($cat['price']) . " تومان";
            $acount =$cat['acount'];
    
            $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteDayPlan" . $id],['text'=>$price,'callback_data'=>"changeDayPlanPrice" . $id],['text'=>$title,'callback_data'=>"changeDayPlanDay" . $id]];
        }
        $keyboard[] = [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]];
        $keyboard[] = [['text' => $buttonValues['back_button'], 'callback_data' => "managePanel"]];
        $msg = ' 📍 برای دیدن جزییات پلن زمانی روی آن بزنید👇';
        
        sendMessage($msg,json_encode([
                'inline_keyboard' => $keyboard
            ]));
    
        
    }else{
        sendMessage("یک مقدار عددی و صحیح وارد کنید");
    }
}
if(preg_match('/^changeDayPlanDay(\d+)/',$data,$match) and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data);
    delMessage();
    sendMessage("روز جدید را وارد کنید:", $cancelKey);
    exit;
}
if(preg_match('/^changeDayPlanDay(\d+)/',$userInfo['step'],$match) && ($from_id == $admin || $userInfo['isAdmin'] == true) and $text != $buttonValues['cancel']) {
    setUser();
    $stmt = $connection->prepare("UPDATE `increase_day` SET `volume` = ? WHERE `id` = ?");
    $stmt->bind_param("ii", $text, $match[1]);
    $stmt->execute();
    $stmt->close();

    sendMessage("✅عملیات با موفقیت انجام شد",$removeKeyboard);
    
    $stmt = $connection->prepare("SELECT * FROM `increase_day`");
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    if($res->num_rows == 0){
       sendMessage( 'لیست پلن های زمانی خالی است ',json_encode([
                'inline_keyboard' => [
                    [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]],
                    [['text'=>$buttonValues['back_button'],'callback_data'=>"managePanel"]]
                ]
            ]));
        exit;
    }
    $keyboard = [];
    $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"تعداد روز",'callback_data'=>"wizwizch"]];
    while($cat = $res->fetch_assoc()){
        $id = $cat['id'];
        $title = $cat['volume'];
        $price=number_format($cat['price']) . " تومان";
        $acount =$cat['acount'];

        $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteDayPlan" . $id],['text'=>$price,'callback_data'=>"changeDayPlanPrice" . $id],['text'=>$title,'callback_data'=>"changeDayPlanDay" . $id]];
    }
    $keyboard[] = [['text' => "افزودن پلن زمانی جدید", 'callback_data' =>"addNewDayPlan"]];
    $keyboard[] = [['text' => $buttonValues['back_button'], 'callback_data' => "managePanel"]];
    $msg = ' 📍 برای دیدن جزییات پلن زمانی روی آن بزنید👇';
    
    sendMessage($msg,json_encode([
            'inline_keyboard' => $keyboard
        ]));

    
}
if($data == 'volumePlanSettings' and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("SELECT * FROM `increase_plan`");
    $stmt->execute();
    $plans = $stmt->get_result();
    $stmt->close();
    
    if($plans->num_rows == 0){
       editText($message_id, 'لیست پلن های حجمی خالی است ',json_encode([
                'inline_keyboard' => [
                    [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]],
                    [['text' => $buttonValues['back_button'],'callback_data'=>"backplan"]]
                    ]]));
        exit;
    }
    $keyboard = [];
    $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"مقدار حجم",'callback_data'=>"wizwizch"]];
    while ($cat = $plans->fetch_assoc()){
        $id = $cat['id'];
        $title = $cat['volume'];
        $price=number_format($cat['price']) . " تومان";
        
        $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteVolumePlan" . $id],['text'=>$price,'callback_data'=>"changeVolumePlanPrice" . $id],['text'=>$title,'callback_data'=>"changeVolumePlanVolume" . $id]];
    }
    $keyboard[] = [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]];
    $keyboard[] = [['text' =>$buttonValues['back_button'], 'callback_data' => "backplan"]];
    $msg = ' 📍 برای دیدن جزییات پلن حجمی روی آن بزنید👇';
    
    $res = editText($message_id, $msg,json_encode([
            'inline_keyboard' => $keyboard
        ]));
    exit;
}
if($data=='addNewVolumePlan' and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data);
    delMessage();
    sendMessage("حجم و قیمت آن را بصورت زیر وارد کنید :
10-30000

مقدار اول حجم (10) گیگابایت
مقدار دوم قیمت (30000) تومان
 ",$cancelKey);
 exit;
}
if($userInfo['step'] == "addNewVolumePlan" and $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)) {
    $input = explode('-',$text); 
    $volume = $input[0];
    $price = $input[1];
    $stmt = $connection->prepare("INSERT INTO `increase_plan` VALUES (NULL, ? ,?)");
    $stmt->bind_param("ii",$volume,$price);
    $stmt->execute();
    $stmt->close();
    
    sendMessage("پلن حجمی جدید با موفقیت اضافه شد",$removeKeyboard);
    sendMessage($mainValues['reached_main_menu'],getAdminKeys());
    setUser();
}
if(preg_match('/^deleteVolumePlan(\d+)/',$data,$match) and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    $stmt = $connection->prepare("DELETE FROM `increase_plan` WHERE `id` = ?");
    $stmt->bind_param("i", $match[1]);
    $stmt->execute();
    $stmt->close();
    alert("پلن موردنظر با موفقیت حذف شد");
    
    
    $stmt = $connection->prepare("SELECT * FROM `increase_plan`");
    $stmt->execute();
    $plans = $stmt->get_result();
    $stmt->close();
    
    if($plans->num_rows == 0){
       editText($message_id, 'لیست پلن های حجمی خالی است ',json_encode([
                'inline_keyboard' => [
                    [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]],
                    [['text' => $buttonValues['back_button'],'callback_data'=>"managePanel"]]
                    ]]));
        exit;
    }
    $keyboard = [];
    $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"مقدار حجم",'callback_data'=>"wizwizch"]];
    while ($cat = $plans->fetch_assoc()){
        $id = $cat['id'];
        $title = $cat['volume'];
        $price=number_format($cat['price']) . " تومان";
        
        $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteVolumePlan" . $id],['text'=>$price,'callback_data'=>"changeVolumePlanPrice" . $id],['text'=>$title,'callback_data'=>"changeVolumePlanVolume" . $id]];
    }
    $keyboard[] = [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]];
    $keyboard[] = [['text' =>$buttonValues['back_button'], 'callback_data' => "managePanel"]];
    $msg = ' 📍 برای دیدن جزییات پلن حجمی روی آن بزنید👇';
    
    $res = editText($message_id, $msg,json_encode([
            'inline_keyboard' => $keyboard
        ]));
}
if(preg_match('/^changeVolumePlanPrice(\d+)/',$data,$match) and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data);
    delMessage();
    sendMessage("قیمت جدید را وارد کنید:", $cancelKey);
    exit;
}
if(preg_match('/^changeVolumePlanPrice(\d+)/',$userInfo['step'],$match) and $text != $buttonValues['cancel'] and ($from_id == $admin || $userInfo['isAdmin'] == true)) {
    $pid=$match[1];
    if(is_numeric($text)){
        $stmt = $connection->prepare("UPDATE `increase_plan` SET `price` = ? WHERE `id` = ?");
        $stmt->bind_param("ii", $text, $pid);
        $stmt->execute();
        $stmt->close();
        sendMessage("عملیات با موفقیت انجام شد",$removeKeyboard);
        
        setUser();
        $stmt = $connection->prepare("SELECT * FROM `increase_plan`");
        $stmt->execute();
        $plans = $stmt->get_result();
        $stmt->close();
        
        if($plans->num_rows == 0){
           sendMessage( 'لیست پلن های حجمی خالی است ',json_encode([
                    'inline_keyboard' => [
                        [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]],
                        [['text' => $buttonValues['back_button'],'callback_data'=>"managePanel"]]
                        ]]));
            exit;
        }
        $keyboard = [];
        $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"مقدار حجم",'callback_data'=>"wizwizch"]];
        while ($cat = $plans->fetch_assoc()){
            $id = $cat['id'];
            $title = $cat['volume'];
            $price=number_format($cat['price']) . " تومان";
            
            $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteVolumePlan" . $id],['text'=>$price,'callback_data'=>"changeVolumePlanPrice" . $id],['text'=>$title,'callback_data'=>"changeVolumePlanVolume" . $id]];
        }
        $keyboard[] = [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]];
        $keyboard[] = [['text' =>$buttonValues['back_button'], 'callback_data' => "managePanel"]];
        $msg = ' 📍 برای دیدن جزییات پلن حجمی روی آن بزنید👇';
        
        $res = sendMessage($msg,json_encode([
                'inline_keyboard' => $keyboard
            ]));
    }else{
        sendMessage("یک مقدار عددی و صحیح وارد کنید");
    }
}
if(preg_match('/^changeVolumePlanVolume(\d+)/',$data) and ($from_id == $admin || $userInfo['isAdmin'] == true)){
    setUser($data);
    delMessage();
    sendMessage("حجم جدید را وارد کنید:", $cancelKey);
    exit;
}
if(preg_match('/^changeVolumePlanVolume(\d+)/',$userInfo['step'], $match) and $text != $buttonValues['cancel'] && ($from_id == $admin || $userInfo['isAdmin'] == true)) {
    $pid=$match[1];
    $stmt = $connection->prepare("UPDATE `increase_plan` SET `volume` = ? WHERE `id` = ?");
    $stmt->bind_param("ii", $text, $pid);
    $stmt->execute();
    $stmt->close();
    sendMessage("✅عملیات با موفقیت انجام شد",$removeKeyboard);
    setUser();

    $stmt = $connection->prepare("SELECT * FROM `increase_plan`");
    $stmt->execute();
    $plans = $stmt->get_result();
    $stmt->close();
    
    if($plans->num_rows == 0){
       sendMessage( 'لیست پلن های حجمی خالی است ',json_encode([
                'inline_keyboard' => [
                    [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]],
                    [['text' => $buttonValues['back_button'],'callback_data'=>"managePanel"]]
                    ]]));
        exit;
    }
    $keyboard = [];
    $keyboard[] = [['text'=>"حذف",'callback_data'=>"wizwizch"],['text'=>"قیمت",'callback_data'=>"wizwizch"],['text'=>"مقدار حجم",'callback_data'=>"wizwizch"]];
    while ($cat = $plans->fetch_assoc()){
        $id = $cat['id'];
        $title = $cat['volume'];
        $price=number_format($cat['price']) . " تومان";
        
        $keyboard[] = [['text'=>"❌",'callback_data'=>"deleteVolumePlan" . $id],['text'=>$price,'callback_data'=>"changeVolumePlanPrice" . $id],['text'=>$title,'callback_data'=>"changeVolumePlanVolume" . $id]];
    }
    $keyboard[] = [['text' => "افزودن پلن حجمی جدید", 'callback_data' =>"addNewVolumePlan"]];
    $keyboard[] = [['text' =>$buttonValues['back_button'], 'callback_data' => "managePanel"]];
    $msg = ' 📍 برای دیدن جزییات پلن حجمی روی آن بزنید👇';
    
    $res = sendMessage( $msg,json_encode([
            'inline_keyboard' => $keyboard
        ]));
    
}
if(preg_match('/^supportCat(.*)/',$data,$match)){
    delMessage();
    sendMessage($mainValues['enter_ticket_title'], $cancelKey);
    setUser("newTicket_" . $match[1]);
}
if(preg_match('/^newTicket_(.*)/',$userInfo['step'],$match)  and $text!=$buttonValues['cancel']){
    setUser($text, 'temp');
	setUser("sendTicket_" . $match[1]);
    sendMessage($mainValues['enter_ticket_description']);
}
if(preg_match('/^sendTicket_(.*)/',$userInfo['step'],$match)  and $text!=$buttonValues['cancel']){
    if(isset($text) || isset($update->message->photo)){
        $ticketCat = $match[1];
        
        $ticketTitle = $userInfo['temp'];
        $time = time();
    
        $ticketTitle = str_replace(["/","'","#"],['\/',"\'","\#"],$ticketTitle);
        $stmt = $connection->prepare("INSERT INTO `chats` (`user_id`,`create_date`, `title`,`category`,`state`,`rate`) VALUES 
                            (?,?,?,?,'0','0')");
        $stmt->bind_param("iiss", $from_id, $time, $ticketTitle, $ticketCat);
        $stmt->execute();
        $inserId = $stmt->get_result();
        $chatRowId = $stmt->insert_id;
        $stmt->close();
        
        $keys = json_encode(['inline_keyboard'=>[
            [['text'=>"پاسخ",'callback_data'=>"reply_{$chatRowId}"]]
            ]]);
        if(isset($text)){
            $txt = "تیکت جدید:\n\nکاربر: <a href='tg://user?id=$from_id'>$first_name</a>\nنام کاربری: @$username\nآیدی عددی: $from_id\n\nموضوع تیکت: $ticketCat\n\nعنوان تیکت: " .$ticketTitle . "\nمتن تیکت: $text";
            $text = str_replace(["/","'","#"],['\/',"\'","\#"],$text);
            $stmt = $connection->prepare("INSERT INTO `chats_info` (`chat_id`,`sent_date`,`msg_type`,`text`) VALUES
                        (?,?,'USER',?)");
            $stmt->bind_param("iis", $chatRowId, $time, $text);
            sendMessage($txt,$keys,"html", $admin);
        }else{
            $txt = "تیکت جدید:\n\nکاربر: <a href='tg://user?id=$from_id'>$first_name</a>\nنام کاربری: @$username\nآیدی عددی: $from_id\n\nموضوع تیکت: $ticketCat\n\nعنوان تیکت: " .$ticketTitle . "\nمتن تیکت: $caption";
            $stmt = $connection->prepare("INSERT INTO `chats_info` (`chat_id`,`sent_date`,`msg_type`,`text`) VALUES
                        (?,?,'USER',?)");
            $text = json_encode(['file_id'=>$fileid, 'caption'=>$caption]);
            $stmt->bind_param("iis", $chatRowId, $time, $text);
            sendPhoto($fileid, $txt,$keys, "HTML", $admin);
        }
        $stmt->execute();
        $stmt->close();
        
        sendMessage("پیام شما با موفقیت ثبت شد",$removeKeyboard,"HTML");
        sendMessage("لطفا یکی از کلید های زیر را انتخاب کنید",getMainKeys());
            
        setUser(NULL,'temp');
    	setUser("none");
    }else{
        sendMessage("پیام مورد نظر پشتیبانی نمی شود");
    }
    
}
if($data== "usersOpenTickets" || $data == "userAllTickets"){
    if($data== "usersOpenTickets"){
        $stmt = $connection->prepare("SELECT * FROM `chats` WHERE `state` != 2 AND `user_id` = ? ORDER BY `state` ASC, `create_date` DESC");
