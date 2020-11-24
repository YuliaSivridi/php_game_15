<?php
require_once 'inccnt_15.php';
require_once 'inclng_15.php';

// send telegram request
function trequest($method, $inputarray) {
    global $bottoken;
    $inputstring = http_build_query($inputarray, null, '&', PHP_QUERY_RFC3986);
    $options = ['http' => ['method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded\r\n',
            'ignore_errors' => true,
            'content' => $inputstring]];
    $request='https://api.telegram.org/bot'.$bottoken.'/'.$method;
    $context = stream_context_create($options);
    $answer = file_get_contents($request, false, $context);
    return $answer;
}

// get user from db
function select_user($dblink, $chat_id) {
	$query_usr = "select * from game_15 where chat_id='".$chat_id."'";
	$result_usr = mysqli_query($dblink, $query_usr);
    return $result_usr;
}

// update user game in db
function update_game($dblink, $chat_id, $tiles) {
	$nline = json_encode($tiles, JSON_UNESCAPED_UNICODE);
	$query_usr = "update game_15 set 
		nline='".$nline."' 
		where chat_id='".$chat_id."'";
	$result_usr = mysqli_query($dblink, $query_usr);
}

// update kbd_id in db to delete then
function update_kbd_id($dblink, $chat_id, $kbd_id) {
	$query_usr = "update game_15 set 
		kbd_id='".$kbd_id."' 
		where chat_id='".$chat_id."'";
	$result_usr = mysqli_query($dblink, $query_usr);
}

// menu keyboard -> main
function kbd_main($lang_usrlng) {
	return $kbd = [[$lang_usrlng['menu_new'], $lang_usrlng['menu_set']]];
}

// menu keyboard -> settings
function kbd_set($lang_usrlng) {
	return $kbd = [[$lang_usrlng['menu_main'], $lang_usrlng['menu_hlp'], $lang_usrlng['menu_lng']]];
}

// make keyboard
function draw_tiles($tiles) {
	$tsize = sqrt(count($tiles));
	$pos_space = array_search(0, $tiles);
	$tkbd = []; $t = 0;
	for ($i = 1; $i <= $tsize; $i++) {
		$tstr = [];
		for ($j = 1; $j <= $tsize; $j++) {
			$text = ($tiles[$t] == 0) ? '__' : $tiles[$t];
			$cb = (($t == ($pos_space - 1)) or ($t == ($pos_space + 1)) or 
			($t == ($pos_space - $tsize)) or ($t == ($pos_space + $tsize))) ? $t : '-';
			$tstr[] = ['text' => $text, 'callback_data' => $cb];
			$t++;
		} $tkbd[] = $tstr;
	} return $tkbd;
}

// get user request
$content = file_get_contents('php://input');
$input = json_decode($content, TRUE);
$dblink = mysqli_connect($dbhost, $dbuser, $dbpswd, $dbname);

// user send msg
if (($input['message']) != null) {
	$chat_id = $input['message']['chat']['id'];
	$user_lang = $input['message']['from']['language_code'];
	$user_msg = trim($input['message']['text']);

	$result_usr = select_user($dblink, $chat_id);
	// user new -> insert to db
	if (mysqli_num_rows($result_usr) <= 0) {
		$user_lang = (array_key_exists($user_lang, $lang)) ? $user_lang : 'ru';
		$query_ins = "insert into game_15 (chat_id, user_lang, user_name) values ('".$chat_id."', '".$user_lang."', 
			'".$input['message']['from']['first_name']." ".$input['message']['from']['last_name']."')";
		$result_ins = mysqli_query($dblink, $query_ins);
		$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['hi1'].$input['message']['from']['first_name'].$lang[$user_lang]['hi2'], 
			'reply_markup' => json_encode(['keyboard' => kbd_main($lang[$user_lang]), 'resize_keyboard' => true])]);

	// user exists
	} else {
		$row = mysqli_fetch_assoc($result_usr);
		$kbd_id = $row['kbd_id']; $user_lang = $row['user_lang'];
		$user_lang = (array_key_exists($user_lang, $lang)) ? $user_lang : 'ru';

		switch ($user_msg) {
			// settings menu -> help
			case '/help': case $lang[$user_lang]['menu_hlp']: {
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['help'],
					'reply_markup' => json_encode(['keyboard' => kbd_set($lang[$user_lang]), 'resize_keyboard' => true])]);
				break;
			}
			// settings menu -> language
			case '/lang': case $lang[$user_lang]['menu_lng']: {
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['lang_ask'],
					'reply_markup' => json_encode(['keyboard' => [['🇬🇧 en', '🇷🇺 ru']], 'resize_keyboard' => true])]);
				break;
			}
			case '🇬🇧 en': case '🇷🇺 ru': {
				$l = explode(' ', $user_msg);
				$user_lang = $l[1];
				$query_lng = "update game_15 set user_lang='".$user_lang."' where chat_id='".$chat_id."'";
				$result_lng = mysqli_query($dblink, $query_lng);
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['lang_ok'],
					'reply_markup' => json_encode(['keyboard' => kbd_set($lang[$user_lang]), 'resize_keyboard' => true])]);
				break;
			}

			// main menu -> settings
			case '/settings': case $lang[$user_lang]['menu_set']: {
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['set_ttl'], 
					'reply_markup' => json_encode(['keyboard' => kbd_set($lang[$user_lang]), 'resize_keyboard' => true])]);
				break;
			}

			// main menu
			case '/main': case $lang[$user_lang]['menu_main']: {
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['main_ttl'], 
					'reply_markup' => json_encode(['keyboard' => kbd_main($lang[$user_lang]), 'resize_keyboard' => true])]);
				break;
			}

			// main menu -> new game
			case '/new': case $lang[$user_lang]['menu_new']: {
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['new'], 
					'reply_markup' => json_encode(['keyboard' => [[$lang[$user_lang]['menu_main'], $lang[$user_lang]['3'], $lang[$user_lang]['4']], 
						[$lang[$user_lang]['5'], $lang[$user_lang]['6'], $lang[$user_lang]['7']]], 'resize_keyboard' => true])]);
				break;
			}

			// new game menu -> 3 / 4 / 5 / 6 / 7
			case $lang[$user_lang]['3']: case $lang[$user_lang]['4']: case $lang[$user_lang]['5']: 
			case $lang[$user_lang]['6']: case $lang[$user_lang]['7']: {
				// make new game array
				$tsize = (int)$user_msg;
				for ($i = 1; $i <= $tsize*$tsize; $i++) {
					$tiles[] = $i;
				} shuffle($tiles);

				// counting parity
				$sum_parity = 0;
				for ($t = 2; $t < $tsize*$tsize; $t++) {
					$pos = array_search($t, $tiles);
					for ($p = $pos+1; $p < $tsize*$tsize; $p++)
						if ($tiles[$p] < $t)
							$sum_parity++;
				} // change max to space
				$pos_max = array_search($tsize*$tsize, $tiles);
				$tiles[$pos_max] = 0;
				if ($tsize % 2 == 0) $sum_parity += (floor($pos_max / $tsize) + 1);
				// swap 2 last tiles if unsolving
				if ($sum_parity % 2 != 0)
					if ($pos_max < 2) { list($tiles[$pos_max+1], $tiles[$pos_max+2]) = array($tiles[$pos_max+2], $tiles[$pos_max+1]); }
					else { list($tiles[0], $tiles[1]) = array($tiles[1], $tiles[0]); }
				update_game($dblink, $chat_id, $tiles);

				// redraw game keyboard
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['game_started'].$tsize.'x'.$tsize,
					'reply_markup' => json_encode(['inline_keyboard' => draw_tiles($tiles)])]);
				$tresponse = json_decode($answer, true);
				$kbd_id = $tresponse['result']['message_id'];
				update_kbd_id($dblink, $chat_id, $kbd_id);
				break;
			}

			default:
				$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['default']]);
				break;
		}
	} mysqli_free_result($result_usr);

// user press button
} else if ($input['callback_query'] != null) {
	$cb_id = $input['callback_query']['id'];
	$chat_id = $input['callback_query']['message']['chat']['id'];
	$cb_data = $input['callback_query']['data'];
	// switch off clock on button
	$answer = trequest('answerCallbackQuery', array('callback_query_id' => $cb_id));

	if ($cb_data != '-') {
		$result_usr = select_user($dblink, $chat_id);
		$row = mysqli_fetch_assoc($result_usr);
		$kbd_id = $row['kbd_id']; $user_lang = $row['user_lang'];
		$user_lang = (array_key_exists($user_lang, $lang)) ? $user_lang : 'ru';
		mysqli_free_result($result_usr);

		// rebuild game array
		$tiles = json_decode($row['nline'], false, 512, JSON_UNESCAPED_UNICODE);
		$pos_space = array_search(0, $tiles);
		list($tiles[$cb_data], $tiles[$pos_space]) = array($tiles[$pos_space], $tiles[$cb_data]);
		update_game($dblink, $chat_id, $tiles);

		// compare with game end
		$tsize = sqrt(count($tiles));
		for ($i = 1; $i <= $tsize*$tsize; $i++) {
			$game_end[] = $i;
		} $game_end[$tsize*$tsize-1] = 0;
		// redraw game keyboard
		if ($tiles == $game_end) {
			$answer = trequest('deleteMessage', ['chat_id' => $chat_id, 'message_id' => $kbd_id]);
			$answer = trequest('sendMessage', ['chat_id' => $chat_id, 'text' => $lang[$user_lang]['game_finished'],
				'reply_markup' => json_encode(['inline_keyboard' => draw_tiles($tiles)])]);
			$tresponse = json_decode($answer, true);
			$kbd_id = $tresponse['result']['message_id'];
			update_kbd_id($dblink, $chat_id, $kbd_id);
		} else {
			$answer = trequest('editMessageReplyMarkup', ['chat_id' => $chat_id, 'message_id' => $kbd_id,
				'reply_markup' => json_encode(['inline_keyboard' => draw_tiles($tiles)])]);
		}
	}
} mysqli_close($dblink); ?>