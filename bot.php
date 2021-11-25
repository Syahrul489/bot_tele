<?php
//masukan nomor token Anda di sini
define('TOKEN','2132099416:AAFdUE48ObP8z7NHG-8uREQ6dWska5RuvQY');
$developer = '1199232531'; // set ID Developer
$botname = "Quiz Bot";

//Fungsi untuk Penyederhanaan kirim perintah dari URI API Telegram
function BotKirim($perintah){
  return 'https://api.telegram.org/bot'.TOKEN.'/'.$perintah;
}

/* Fungsi untuk mengirim "perintah" ke Telegram
* Perintah tersebut bisa berupa
*  -SendMessage = Untuk mengirim atau membalas pesan
*  -SendSticker = Untuk mengirim pesan
*  -Dan sebagainya, Anda bisa memm
* 
* Adapun dua fungsi di sini yakni pertama menggunakan
* stream dan yang kedua menggunkan curl
* 
* */
function KirimPerintahStream($perintah,$data){
   $options = array(
      'http' => array(
          'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
          'method'  => 'POST',
          'content' => http_build_query($data),
      ),
  );
  $context  = stream_context_create($options);
  $result = file_get_contents(BotKirim($perintah), false, $context);
  return $result;
}
 
function KirimPerintahCurl($perintah,$data){
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL,BotKirim($perintah));
  curl_setopt($ch, CURLOPT_POST, count($data));
  curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($data));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 
  $kembali = curl_exec ($ch);
  curl_close ($ch);
 
  return $kembali;
}
 
 
/*  Perintah untuk mendapatkan Update dari Api Telegram.
*  Fungsi ini menjadi penting karena kita menggunakan metode "Long-Polling".
*  Jika Anda menggunakan webhooks, fungsi ini tidaklah diperlukan lagi.
*/
 
function DapatkanUpdate($offset) 
{
  //kirim ke Bot
  $url = BotKirim("getUpdates")."?offset=".$offset;
  //dapatkan hasilnya berupa JSON
  $kirim = file_get_contents($url);
  //kemudian decode JSON tersebut
  $hasil = json_decode($kirim, true);
  if ($hasil["ok"]==1)
      {
          /* Jika hasil["ok"] bernilai satu maka berikan isi JSONnya.
           * Untuk dipergunakan mengirim perintah balik ke Telegram
           */
          return $hasil["result"];
      }
  else
      {   /* Jika tidak maka kosongkan hasilnya.
           * Hasil harus berupa Array karena kita menggunakan JSON.
           */
          return array();
      }
}
 
function KirimPerintah($perintah,$data){
	global $developer;

	if(isset($data['chat_id']) && isset($data['text'])){	
		file_put_contents("KuisMayoritasBot\chatlog.txt", "Pada " . $data['chat_id'] . ": " . $data['text'] . "\r\n", FILE_APPEND | LOCK_EX);
		
		//jika merupakan command dari developer
		if(substr($data['chat_id'],0,3) == "dev"){
			$data['text'] = str_replace("dev","TO ",$data['chat_id']) . ": " . $data['text'];
			$data['chat_id'] = $developer;
		}
	}
	
	// Detek otomatis metode curl atau stream 
	if(is_callable('curl_init')) {
		$hasil = KirimPerintahCurl($perintah,$data);
		//cek kembali, terkadang di XAMPP Curl sudah aktif
		//namun pesan tetap tidak terikirm, maka kita tetap gunakan Stream
		if (empty($hasil)){
			$hasil = KirimPerintahStream($perintah,$data);
		}   
	} else {
		 $hasil = KirimPerintahStream($perintah,$data);
	}
	
	//kalau tidak ada hasil
	if (empty($hasil)){
		echo "ERROR: ";
		print_r($data);
	}
	
	return $hasil;  
}

//set variables
$update_id  = 0; //mula-mula tepatkan nilai offset pada nol
$fakemsg = array();
$gamedata = array();
$jeda = 5;

//set daftar pertanyaan
$pertanyaans = file_get_contents("KuisMayoritasBot\daftar_pertanyaan.txt");
$pertanyaans = explode("\n",$pertanyaans);
shuffle($pertanyaans);

echo "bot diaktifan \n";

while(true) {
	//baca JSON dari bot, cek dan dapatkan pembaharuan JSON nya, set update_id berikutnya
	$updates = DapatkanUpdate($update_id);	
	
	if(!empty($fakemsg)){
		$message["update_id"] = $update_id;
		$message["message"]["text"] = $fakemsg['text'];
		$message["message"]["from"]["id"] = $fakemsg['from'];
		$message["message"]["chat"]["type"] = $fakemsg['type'];
		$message["message"]["from"]["username"] = $fakemsg['name'];
		$message["message"]["chat"]["id"] = $fakemsg['id'];
		$message["message"]["message_id"] = "dev";
		$message["message"]["from"]["first_name"] = $fakemsg['name'];
		$message["message"]["from"]["last_name"] = "dev";
		

		array_push($updates, $message);
		$fakemsg = array();
	}
	
	//untuk setiap pesan yang masuk:=========================================================
	foreach ($updates as $message){

		//set variables
		$update_id = 1+$message["update_id"];
		$message_data = $message["message"];
		$chatid = (string) $message_data["chat"]["id"];
		$message_id = $message_data["message_id"];
		$dari = $message_data["from"]["id"];
		$dari_user = $message_data["from"]["username"];
		$nama = $message_data["from"]["first_name"] . " " . $message_data["from"]["last_name"];
		$jenis = $message_data["chat"]["type"]; //hasilnya "private" atau "group" atau "supergroup"
		
		//kalau di add ke group
		if (isset($message_data['new_chat_member'])) {
			if($message_data['new_chat_member']['username'] == $botname){
				$output="Halo semuanya, saya adalah pembawa acara KUIS MAYORITAS.\n";
				$output.="Gunakan /join untuk berpartisipasi.";
				$data = array(
					'chat_id' => $chatid,
					'text'=> $output,
					'parse_mode'=>'Markdown',
					'reply_to_message_id' => $message_id
					);
				$hasil = KirimPerintah('sendMessage',$data);
			}			
		}
		
		//jika terdapat text dari Pengirim
		if (isset($message_data["text"])) {

			$text = trim($message_data["text"]);
			$isi = $message_data["text"];
			
			//tampilkan di console
			if($jenis == "private" or ($jenis == "group" and substr($text,0,1) == "/")){
				echo "$dari_user($jenis): $text \n";
			}
			
			//tulis log file
			file_put_contents("KuisMayoritasBot\chatlog.txt", "Dari $dari ($jenis): $isi \r\n", FILE_APPEND | LOCK_EX);
			
			//cek apakah pemain atau non-pemain
			$pemain = false;
			$di_grup = "";
			foreach($gamedata as $key=>$value){
				if(!isset($gamedata[$key]['player'])){
					$gamedata[$key]['player'] = array();
				}
				if(array_key_exists($dari,$gamedata[$key]['player'])){
					$pemain = true;
					$di_grup = $key;
				}
			}

			//jika ada command di supergroup	
			if($jenis == "supergroup"){
				$jenis = "group";
			}
			
			//jika ada command menggunakan @
			if(substr($text,0,1) == "/"){
				$text = str_replace("@$botname","",$text);
			}
			
			//jika private dari user
			if($jenis == 'private'){
				
				//kalau developer
				if($dari == $developer and strtolower($text) == "/getgroups"){
					print_r($gamedata);
					$output="Group: \n \n";
					foreach($gamedata as $key=>$value){
						$output.= "$key";
						$playernamenya = array();
						foreach($value['player'] as $key2=>$value2){
							array_push($playernamenya,$key2."@".$value2['username']);
						}
						$output .= " (" . implode(", ",$playernamenya) . ")";
						$output .= "\n \n";
					}
					$data = array(
						'chat_id' => $chatid,
						'text'=> $output,
						'parse_mode'=>'Markdown',
						'reply_to_message_id' => $message_id
						);
					$hasil = KirimPerintah('sendMessage',$data);
				}
				if($dari == $developer and strtolower(substr($text,0,3)) == "dev"){
					$commands = explode(";",$text);
					//get grup
					if(substr(strtolower($commands[1]),0,8) == 'getgroup'){
						$output="Gunakan perintah ini: /getgroups";
						$data = array(
							'chat_id' => $chatid,
							'text'=> $output,
							'parse_mode'=>'Markdown',
							'reply_to_message_id' => $message_id
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
					//terima pesan palsu
					elseif(strtolower($commands[1]) == 'terima'){	//contoh command: "dev;terima;id_pengirim;nama_pengirim;PM/-IDGROUP;text"
						$fakemsg['from'] = $commands[2];
						$fakemsg['name'] = $commands[3];
						if(strtolower($commands[4])=="pm"){
							$fakemsg['type'] = "private";
							$fakemsg['id'] = "dev" . $commands[2];
						}else{
							$fakemsg['type'] = "group";
							$fakemsg['id'] = $commands[4];
						}
						$fakemsg['text'] = $commands[5];
						$output="OKAY";
						$data = array(
							'chat_id' => $chatid,
							'text'=> $output,
							'parse_mode'=>'Markdown',
							'reply_to_message_id' => $message_id
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
					elseif(strtolower($commands[1]) == 'kirim'){	//contoh command: "dev;kirim;ID_TUJUAN;text"
						$data = array(
							'chat_id' => $commands[2],
							'text'=> $commands[3],
							'parse_mode'=>'Markdown'
							);
						$hasil = KirimPerintah('sendMessage',$data);
						if (!empty($hasil)){
							$output="Berhasil";
							$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
							$hasil = KirimPerintah('sendMessage',$data);
						}
					}
					elseif(strtolower($commands[1]) == 'setscore'){	//contoh command: "dev;setscore;ID_GROUP;ID_PEMAIN;score"
						$data = array(
							'chat_id' => $chatid,
							'text'=> "OKAY",
							'parse_mode'=>'Markdown'
							);
						$hasil = KirimPerintah('sendMessage',$data);
						if (!empty($hasil)){
							$grupidnya = $commands[2];
							$pemainnya = $commands[3];
							$scorenya = $commands[4];
							$gamedata[$grupidnya]['player'][$pemainnya]['score'] = $scorenya;
						}
					}
				}
				else{
					//kalau bukan pemain
					if($pemain == false){
						if(strtolower($text) == '/start'){
							$output="Selamat datang $nama @$dari_user. Untuk menggunakan bot ini, invite saya ke grup anda. Bantuan: /help \n
Anda juga bisa mengikuti public group t.me/KuisMayoritas";
							$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
							$hasil = KirimPerintah('sendMessage',$data);
						}
						elseif(strtolower($text) == '/help'){
							$output="Untuk meng-invite bot ini ke grup yang sudah ada, tap nama saya di atas, lalu tap titik-titik di kanan atas, lalu pilih Add to group. \n \n
Anda juga bisa mengikuti public group t.me/KuisMayoritas";
							$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
							$hasil = KirimPerintah('sendMessage',$data);
						}else{
							$output="Gunakan /help untuk menampilkan daftar komando yang dapat dijalankan.";
							$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
							$hasil = KirimPerintah('sendMessage',$data);
						}
					}
					//kalau pemain
					else{
						if(strtolower($text) == '/help'){
							$output="Saat ini anda sedang dalam permainan. Silakan jalankan command ini di grup untuk opsi lebih lanjut.";
							$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
							$hasil = KirimPerintah('sendMessage',$data);
						}
						//kalau pertanyaan sudah diajukan
						elseif(isset($gamedata[$di_grup]['step'])){
							if($gamedata[$di_grup]['step'] == '2' or $gamedata[$di_grup]['step'] == '3' ){
								//kalau pemain baru kirim /start
								if(strtolower($text) == "/start"){
									$output="Selamat datang $nama @$dari_user. Ayo segera jawab pertanyaan yang sudah diajukan di grup.";
								}else{
									$gamedata[$di_grup]['player'][$dari]['last_jwb'] = strtoupper(str_replace("-"," ",$text));
									$gamedata[$di_grup]['idle'] = '0'; // reset idle time
									$output="Jawaban ini dikunci. Anda masih dapat mengubahnya bila masih ada waktu.";
								}
								$data = array(
									'chat_id' => $chatid,
									'text'=> $output,
									'parse_mode'=>'Markdown',
									'reply_to_message_id' => $message_id
									);
								$hasil = KirimPerintah('sendMessage',$data);
							}
						}
					}
				}
			}
			
			//jika ada command di grup
			elseif($jenis == "group" and substr($text,0,1) == "/"){ 
				$output = "";
				
				//cek keberadaan grup di gamedata
				if(!isset($gamedata[$chatid])){
					//jika masih kosong, kocok dulu pertanyaannya
					if(empty($gamedata)){
						echo "Pertanyaan dikocok. \n";
						shuffle($pertanyaans);
					}
					//jika tidak ada, tambahkan
					$gamedata[$chatid]['started'] = '0';
					$gamedata[$chatid]['player'] = array();
					//laporkan ke developer
					echo $output = "New group: $chatid \n";
					$data = array(
						'chat_id' => $developer,
						'text'=> $output,
						'parse_mode'=>'Markdown'
						);
					$hasil = KirimPerintah('sendMessage',$data);
				}
				$gamedata[$chatid]['idle'] = '0'; // reset idle time
				$started = $gamedata[$chatid]['started'];
				$playercount = count($gamedata[$chatid]['player']);
				
				//kalau bukan pemain
				if($pemain == false){
					if(strtolower($text) == '/join'){
						$gamedata[$chatid]['player'][$dari]['nama'] = $nama;
						$gamedata[$chatid]['player'][$dari]['username'] = $dari_user;
						$gamedata[$chatid]['player'][$dari]['score'] = 0;
						$gamedata[$chatid]['player'][$dari]['lastscore'] = "";
						$gamedata[$chatid]['player'][$dari]['last_jwb'] = "";
						$gamedata[$chatid]['player'][$dari]['tdk_jwb'] = 0;
						$playercount+=1;
						$output="$nama mengikuti permainan. Jumlah pemain saat ini: $playercount. \n";
						if($playercount >= 3 and $gamedata[$chatid]['started'] == '0'){
							//jika grup khusus, mulai otomatis
							if($chatid == "-1001078921435"){
								$started = '1';
								$gamedata[$chatid]['started'] = '1';
								$gamedata[$chatid]['step'] = '0';
								$gamedata[$chatid]['steptime'] = '0';
								$gamedata[$chatid]['nopert'] = rand ( 0 , count($pertanyaans)-1 );
								$output .= "Permainan akan segera dimulai.";	
							}else{
								$output.= "Untuk memulai permainan, gunakan /start";
							}
						}
						$data = array(
							'chat_id' => $chatid,
							'text'=> $output,
							'parse_mode'=>'Markdown',
							'reply_to_message_id' => $message_id
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
					if(strtolower($text) == '/help'){
						$output = "";
						if($started != '0'){
							$output .= "/score - Menampilkan daftar pemain dan skornya masing-masing.\n";
						}
						$output .= "/join - Mengikuti permainan.\n";
						$output .= "/rule - Menampilkan aturan main.";
						$data = array(
							'chat_id' => $chatid,
							'text'=> $output,
							'parse_mode'=>'Markdown',
							'reply_to_message_id' => $message_id
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
				}
				//kalau pemain
				else{
					if(strtolower($text) == '/help'){
						$output = "";
						if($started != '0'){
							$output .= "/score - Menampilkan daftar pemain dan skornya masing-masing.\n";
							$output .= "/end - Mengakhiri permainan.\n";
							if(isset($gamedata[$chatid]['paused'])){
								$output .= "/resume - Melanjutkan permainan.\n";
							}else{
								$output .= "/pause - Menghentikan permainan sementara.\n";
							}
						}else{
							$output .= "/start - Memulai permainan.\n";
						}
						$output .= "/leave - Meniggalkan permainan.\n";
						$output .= "/rule - Menampilkan aturan main.";
						$data = array(
							'chat_id' => $chatid,
							'text'=> $output,
							'parse_mode'=>'Markdown',
							'reply_to_message_id' => $message_id
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
					if(strtolower($text) == '/leave'){
						unset($gamedata[$chatid]['player'][$dari]);
						$playercount -= 1;
						$endaja = "";
						if($playercount == 3){
							$endaja = "\n";
							$endaja .= "Untuk mengakhiri permainan, gunakan /end (bukan leave)";
						}
						$output = "$nama meninggalkan permainan. Jumlah pemain saat ini: $playercount. $endaja";
						$data = array(
							'chat_id' => $chatid,
							'text'=> $output,
							'parse_mode'=>'Markdown',
							'reply_to_message_id' => $message_id
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
					//kalau belum mulai dan dikasih perintah start
					if($started == '0' and strtolower($text) == '/start'){
						//kalau jumlah pemain kurang dari 3 orang
						if($playercount < 3){
							$output = "Jumlah pemain saat ini: $playercount. Untuk memulai permainan, diperlukan minimal 3 pemain.";
						}
						//kalau jumlah pemain cukup
						else{
							$started = '1';
							$gamedata[$chatid]['started'] = '1';
							$gamedata[$chatid]['step'] = '0';
							$gamedata[$chatid]['steptime'] = '0';
							$gamedata[$chatid]['nopert'] = rand ( 0 , count($pertanyaans)-1 );
							$output = "Permainan akan segera dimulai.";							
						}
						$data = array(
							'chat_id' => $chatid,
							'text'=> $output,
							'parse_mode'=>'Markdown',
							'reply_to_message_id' => $message_id
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
					//kalau permainan sudah dimulai dan diakhiri dengan /end
					if($started == '1' and strtolower($text) == '/end'){
						if($chatid == "-1001078921435"){
							//kalau di spesial grup kuis mayoritas
							$output = "Permainan di group ini tidak dapat dihentikan. Gunakan /leave untuk meninggalkan permainan.";
							$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
							$hasil = KirimPerintah('sendMessage',$data);
						}else{
							$output = "Permainan berakhir. Terima kasih atas partisipasinya, sampai jumpa di Kuis Mayoritas episode berikutnya! Wassalam \n \n";
							$output .= "Skor akhir: \n";
							$scores = array();
							foreach($gamedata[$chatid]['player'] as $key=>$value){
								$namanya = $value['nama'];
								$skornya = $value['score'];
								echo "$namanya: $skornya \n";
								$scores[$namanya] = $skornya;
							}
							arsort($scores);
							foreach($scores as $key=>$value){
								$output .= "$key: $value \n";
							}						
							$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
							$hasil = KirimPerintah('sendMessage',$data);
							if(!empty($hasil)){
								$started = '0';
								$gamedata[$chatid]['started'] = '0';								
								$gamedata[$chatid]['player'] = array();
								$output = "End Group: $chatid";
								$data = array(
									'chat_id' => $developer,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
								$hasil = KirimPerintah('sendMessage',$data);
							}
						}
					}
					if($text == "/score" and $started == '1'){
						$output = "Score: \n";
						$scores = array();
						foreach($gamedata[$chatid]['player'] as $key=>$value){
							$namanya = $value['nama'];
							$skornya = $value['score'];
							echo "$namanya: $skornya \n";
							$scores[$namanya] = $skornya;
						}
						arsort($scores);
						foreach($scores as $key=>$value){
							$output .= "$key: $value \n";
						}
						$data = array(
									'chat_id' => $chatid,
									'text'=> $output,
									'parse_mode'=>'Markdown',
									'reply_to_message_id' => $message_id
									);
						$hasil = KirimPerintah('sendMessage',$data);
					}
					if($text == "/pause" and $started == '1' and !isset($gamedata[$chatid]['paused'])){
						$output = "Permainan diberhentikan sementara. Lanjutkan dengan /resume";
						$data = array(
									'chat_id' => $chatid,
									'text'=> $output,
									'parse_mode'=>'Markdown',
									'reply_to_message_id' => $message_id
									);
						$hasil = KirimPerintah('sendMessage',$data);
						if(!empty($hasil)){
							$gamedata[$chatid]['paused'] = 1;
						}
					}
					if($text == "/resume" and $started == '1' and isset($gamedata[$chatid]['paused'])){
						$output = "Permainan dilanjutkan";
						$data = array(
									'chat_id' => $chatid,
									'text'=> $output,
									'parse_mode'=>'Markdown',
									'reply_to_message_id' => $message_id
									);
						$hasil = KirimPerintah('sendMessage',$data);
						if(!empty($hasil)){
							unset($gamedata[$chatid]['paused']);
						}
					}
				}
				if($text == "/rule"){
					$output = "Skor untuk jawaban anda adalah jumlah orang lain yang jawabannya sama dengan anda dikali 10. https://gjberkarya.blogspot.co.id/2016/12/game-kuis-mayoritas-dengan-telegram.html";
					$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
					$hasil = KirimPerintah('sendMessage',$data);
				}
				
				//kalau tidak ada output, tampilkan help
				if($output == ""){
					$output = "Gunakan /help untuk menampilkan daftar komando yang dapat dijalankan.";
					$data = array(
								'chat_id' => $chatid,
								'text'=> $output,
								'parse_mode'=>'Markdown',
								'reply_to_message_id' => $message_id
								);
					$hasil = KirimPerintah('sendMessage',$data);
				}
			}
		}    	
	}//========================================
	
	//untuk tiap grup
	if(!empty($gamedata)){
		$inactivegroup = array();
		foreach($gamedata as $key=>$value){
			if($value['started'] == '1' and !isset($value['paused'])){
				//jika player nya kurang dari 3
				if(count($value['player'])<3){
					//hentikan permainan					
					$output = "Pemain kurang dari 3 orang, permainan terpaksa dihentikan. Lain kali sebaiknya gunakan /end \n \n";
					$data = array(
						'chat_id' => $key,
						'text'=> $output,
						'parse_mode'=>'Markdown'
						);
					$hasil = KirimPerintah('sendMessage',$data);
					if($hasil != ""){
						$gamedata[$key]['started'] = '0';
						$gamedata[$key]['player'] = array();
						$output = "End Group: $key";
						$data = array(
							'chat_id' => $developer,
							'text'=> $output,
							'parse_mode'=>'Markdown'
							);
						$hasil = KirimPerintah('sendMessage',$data);
					}
				}
				//jika player nya 3 atau lebih 
				else{
					
					//step 0
					if($value['step'] == '0' and $value['steptime']>5){
						$output = "Permainan Dimulai! \n";
						$output .= "Pertanyaan akan dikirim di grup ini, namun jawaban harus anda kirimkan melalui private message ke saya @$botname";
						$data = array(
									'chat_id' => $key,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
						$hasil = KirimPerintah('sendMessage',$data);
						$gamedata[$key]['steptime'] = 0;
						$gamedata[$key]['step'] = '1';
					}
					//step 1
					elseif($value['step'] == '1' and $value['steptime']>=5){
						$nopert = $gamedata[$key]['nopert'];
						$pertanyaan = ucfirst($pertanyaans[$nopert]);
						$output = "PERTANYAAN \n$pertanyaan";
						$data = array(
									'chat_id' => $key,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
						$hasil = KirimPerintah('sendMessage',$data);
						$gamedata[$key]['nopert'] += 1;
						if($gamedata[$key]['nopert'] >= count($pertanyaans)){
							$gamedata[$key]['nopert'] = 0;
						}
						$gamedata[$key]['steptime'] = 0;
						$gamedata[$key]['step'] = '2';
					}
					//step 2
					elseif($value['step'] == '2' and $value['steptime']>=5){
						$output = "Anda memiliki waktu 1 menit untuk menjawabnya. Kirimkan jawaban anda ke t.me/$botname";
						$data = array(
									'chat_id' => $key,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
						$hasil = KirimPerintah('sendMessage',$data);
						$gamedata[$key]['steptime'] = 0;
						$gamedata[$key]['step'] = '3';
					}
					//step 3
					elseif($value['step'] == '3' and $value['steptime']>=40){
						$output = "Waktu untuk menjawab tinggal 15 detik lagi. Pastikan sudah ada jawaban yang dikunci.";
						$data = array(
									'chat_id' => $key,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
						$hasil = KirimPerintah('sendMessage',$data);
						$gamedata[$key]['steptime'] = 0;
						$gamedata[$key]['step'] = '4';
					}
					//step 4
					elseif($value['step'] == '4' and $value['steptime']>=15){
						$output = "Waktu habis! \n";
						$output .= "$pertanyaan \n";
						$jawaban_pemain = array();
						foreach($gamedata[$key]['player'] as $key2=>$value2){
							$jawabannya = $value2['last_jwb'];
							if($jawabannya != ""){
								$jawaban_pemain[$key2] = $jawabannya;
							}
						}
						$hitung_jawaban = array_count_values($jawaban_pemain);
						foreach($gamedata[$key]['player'] as $key2=>$value2){
							$jawabannya = $value2['last_jwb'];
							if($jawabannya == ""){
								//kalau jawaban kosong
								$gamedata[$key]['player'][$key2]['tdk_jwb'] += 1;
								$gamedata[$key]['player'][$key2]['lastscore'] = " tidak menjawab (" . $gamedata[$key]['player'][$key2]['tdk_jwb'] . "x)";
								$output .= $value2['nama'] . $gamedata[$key]['player'][$key2]['lastscore'] . "\n";
							}else{
								//kalau jawaban tidak kosong
								$hitungnya = $hitung_jawaban[$jawabannya];
								$skornya = ($hitungnya-1)*10;
								if($skornya>0){
									$gamedata[$key]['player'][$key2]['lastscore'] = "(+$skornya)";
									$gamedata[$key]['player'][$key2]['score'] += $skornya;
								}else{
									$gamedata[$key]['player'][$key2]['lastscore'] = "(0)";
								}
								$keterangan = $gamedata[$key]['player'][$key2]['lastscore'];
								$output .= $value2['nama'] . ": $jawabannya $keterangan \n";
							}
						}
						$data = array(
									'chat_id' => $key,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
						$hasil = KirimPerintah('sendMessage',$data);
						$gamedata[$key]['steptime'] = 0;
						$gamedata[$key]['step'] = '5';
					}
					//step 5
					elseif($value['step'] == '5' and $value['steptime']>=5){
						$output = "Score: \n";
						$leave = "\n";
						$scores = array();
						$ada_yg_tdk_jwb = false;
						foreach($gamedata[$key]['player'] as $key2=>$value2){
							$namanya = $value2['nama'];
							$skornya = $value2['score'];
							$scores[$namanya] = "$skornya";
							
							//kalau tidak jawab
							if($value2['last_jwb'] == ""){
								$ada_yg_tdk_jwb = true;
								// kalau 3x tidak jawab
								if($value2['tdk_jwb'] >= 3){
									unset($gamedata[$key]['player'][$key2]);
									$leave .= "$namanya meninggalkan permainan.\n";
								}
							}else{
								//bersihkan last jawab dan last score
								$gamedata[$key]['player'][$key2]['lastscore'] = "";
								$gamedata[$key]['player'][$key2]['last_jwb'] = "";	
								$gamedata[$key]['player'][$key2]['tdk_jwb'] = 0;								
							}
						}
						arsort($scores);
						foreach($scores as $key2=>$value2){
							$output .= "$key2: $value2 \n";
						}
						$output .= $leave;
						if($ada_yg_tdk_jwb){
							$output .= "\n";
							$output .= "Pemain yang 3x tidak menjawab akan dikeluarkan dari permainan.";
						}
						$data = array(
									'chat_id' => $key,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
						$hasil = KirimPerintah('sendMessage',$data);
						$gamedata[$key]['steptime'] = 0;
						$gamedata[$key]['step'] = '6';
					}
					//step 6
					elseif($value['step'] == '6' and $value['steptime']>=15){
						$output = "Siap-siap untuk pertanyaan berikutnya! \n";
						$output .= "opsi: /help";
						$data = array(
									'chat_id' => $key,
									'text'=> $output,
									'parse_mode'=>'Markdown'
									);
						$hasil = KirimPerintah('sendMessage',$data);
						$gamedata[$key]['steptime'] = 0;
						$gamedata[$key]['step'] = '1';
					}
					$gamedata[$key]['steptime'] += $jeda;
				};
			}
			
			//kalau group ini tidak aktif selama 600 detik
			if($gamedata[$key]['idle'] >=600){
				$data = array(
							'chat_id' => $key
							);
				$hasil = KirimPerintah('leaveChat',$data);
				array_push($inactivegroup,$key);
			}
			
			// tambah idle time untuk group selain -1001078921435 (t.me/KuisMayoritas)
			if($key != "-1001078921435"){
				$gamedata[$key]['idle'] += $jeda;
			}
		}
		foreach($inactivegroup as $value){
			if(!empty($value)){
				unset($gamedata[$value]);
			}
		}
	}
	
	echo ".";
	sleep($jeda);
}


























?>