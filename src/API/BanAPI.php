<?php

/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

이 프로그램은 자유 소프트웨어입니다: 당신은 LGPL 라이센스 3 또는 최신 버전 하에서 수정하거나 (또는 수정하지 않고 )이 프로그램을 재배포할 수 있습니다.


번역: MCPE KOREA

*/

class BanAPI{
	private $server;
	private $whitelist;
	private $banned;
	private $ops;
	private $bannedIPs;
	private $cmdWL = array();//커맨드 화이트리스트
	function __construct(){
		$this->server = ServerAPI::request();
	}
	
	public function init(){
		$this->whitelist = new Config(DATA_PATH."white-list.txt", CONFIG_LIST);//Open whitelist list file
		$this->bannedIPs = new Config(DATA_PATH."banned-ips.txt", CONFIG_LIST);//Open Banned IPs list file
		$this->banned = new Config(DATA_PATH."banned.txt", CONFIG_LIST);//Open Banned Usernames list file
		$this->ops = new Config(DATA_PATH."ops.txt", CONFIG_LIST);//Open list of OPs
		$this->server->api->console->register("banip", "<add|remove|list|reload> [IP|player]", array($this, "commandHandler"));
		$this->server->api->console->register("ban", "<add|remove|list|reload> [username]", array($this, "commandHandler"));
		$this->server->api->console->register("kick", "<player> [reason ...]", array($this, "commandHandler"));
		$this->server->api->console->register("whitelist", "<on|off|list|add|remove|reload> [username]", array($this, "commandHandler"));
		$this->server->api->console->register("op", "<player>", array($this, "commandHandler"));
		$this->server->api->console->register("deop", "<player>", array($this, "commandHandler"));
		$this->server->api->console->register("sudo", "<player>", array($this, "commandHandler"));
		$this->server->api->console->alias("ban-ip", "banip add");
		$this->server->api->console->alias("banlist", "ban list");
		$this->server->api->console->alias("pardon", "ban remove");
		$this->server->api->console->alias("pardon-ip", "banip remove");
		$this->server->addHandler("console.command", array($this, "permissionsCheck"), 1);//Event handler when commands are issued. Used to check permissions of commands that go through the server.
		$this->server->addHandler("player.block.break", array($this, "permissionsCheck"), 1);//Event handler for blocks
		$this->server->addHandler("player.block.place", array($this, "permissionsCheck"), 1);//Event handler for blocks
		$this->server->addHandler("player.flying", array($this, "permissionsCheck"), 1);//Flying Event
	}
	
	public function cmdWhitelist($cmd){//OP가 아닌 사람들도 쓸 수 있게 명령어 화이트리스트를 생성합니다.
		$this->cmdWhitelist[strtolower(trim($cmd))] = true;
	}
	
	public function isOp($username){//플레이어가 OP권한이 있는가?
		$username = strtolower($username);
		if($this->server->api->dhandle("op.check", $username) === true){
			return true;
		}elseif($this->ops->exists($username)){
			return true;
		}
		return false;	
	}
	
	public function permissionsCheck($data, $event){
		switch($event){
			case "player.flying"://OP권한이 있으면 서버에서 날 수 있습니다.
				if($this->isOp($data->iusername)){
					return true;
				}
				break;
			case "player.block.break":
			case "player.block.place"://스폰 지역 보호. OP만 스폰 지역에서 블럭을 놓고 부술 수 있습니다.
				if(!$this->isOp($data["player"]->iusername)){
					$t = new Vector2($data["target"]->x, $data["target"]->z);
					$s = new Vector2($this->server->spawn->x, $this->server->spawn->z);
					if($t->distance($s) <= $this->server->api->getProperty("spawn-protection") and $this->server->api->dhandle($event.".spawn", $data) !== true){
						return false;
					}
				}
				return;
				break;
			case "console.command"://현재 부여된 플레이어의 OP권한의 유무에 따라 커맨드 사용 권한을 정의합니다.
				if(isset($this->cmdWhitelist[$data["cmd"]])){
					return;
				}
				
				if($data["issuer"] instanceof Player){
					if($this->server->api->handle("console.check", $data) === true or $this->isOp($data["issuer"]->iusername)){
						return;
					}
				}elseif($data["issuer"] === "console" or $data["issuer"] === "rcon"){
					return;
				}
				return false;
			break;
		}
	}
	
	public function commandHandler($cmd, $params, $issuer, $alias){
		$output = "";
		switch($cmd){
			case "sudo":
				$target = strtolower(array_shift($params));
				$player = $this->server->api->player->get($target);
				if(!($player instanceof Player)){
					$output .= "플레이어가 연결되어 있지 않습니다.\n";
					break;
				}
				$this->server->api->console->run(implode(" ", $params), $player);
				$output .= "Command ran as ".$player->username.".\n";
				break;
			case "op":
				$user = strtolower($params[0]);
				$player = $this->server->api->player->get($user);
				if(!($player instanceof Player)){
					$this->ops->set($user);
					$this->ops->save($user);
					$output .= $user."님은 이제 OP입니다.\n";
					break;
				}
				$this->ops->set($player->iusername);
				$this->ops->save();
				$output .= $player->iusername."님은 이제 OP입니다\n";
				$this->server->api->chat->sendTo(false, "당신은 이제 OP입니다.", $player->iusername);
				break;
			case "deop":
				$user = strtolower($params[0]);
				$player = $this->server->api->player->get($user);
				if(!($player instanceof Player)){
					$this->ops->set($user, false);
					$this->ops->save($user);
					$output .= $user."님은 이제 OP가 아닙니다.\n";
					break;
				}
				$this->ops->remove($player->iusername);
				$this->ops->save();
				$output .= $player->iusername."님은 이제 OP가 아닙니다.\n";
				$this->server->api->chat->sendTo(false, "당신은 이제 OP가 아닙니다.", $player->iusername);
				break;
			case "kick":
				if(!isset($params[0])){
					$output .= "Usage: /kick <player> [reason ...]\n";
				}else{
					$name = strtolower(array_shift($params));
					$player = $this->server->api->player->get($name);
					if($player === false){
						$output .= "플레이어 \"".$name."\"님은 존재하지 않는 이름입니다.\n";
					}else{
						$reason = implode(" ", $params);
						$reason = $reason == "" ? "이유 없음":$reason;
						$player->close("당신은 서버에서 다음의 이유로 강퇴당했습니다: ".$reason);
						if($issuer instanceof Player){
							$this->server->api->chat->broadcast($player->username."님은  ".$issuer->username."님에 의해 다음의 이유로 강퇴당했습니다: $reason\n");
						}else{
							$this->server->api->chat->broadcast($player->username."님은 다음의 이유로 강퇴당했습니다: $reason\n");
						}
					}
				}
				break;
			case "whitelist":
				$p = strtolower(array_shift($params));
				switch($p){
					case "remove":
						$user = strtolower($params[0]);
						$this->whitelist->remove($user);
						$this->whitelist->save();
						$output .= "플레이어 \"$user\"님이 화이트리스트로부터 제외되었습니다.\n";
						break;
					case "add":
						$user = strtolower($params[0]);
						$this->whitelist->set($user);
						$this->whitelist->save();
						$output .= "플레이어 \"$user\"님이 화이트리스트에 추가되었습니다.\n";
						break;
					case "reload":
						$this->whitelist = new Config(DATA_PATH."white-list.txt", CONFIG_LIST);
						break;
					case "list":
						$output .= "화이트리스트: ".implode(", ", $this->whitelist->getAll(true))."\n";
						break;
					case "on":
					case "true":
					case "1":
						$output .= "화이트리스트가 활성화되었습니다\n";
						$this->server->api->setProperty("white-list", true);
						break;
					case "off":
					case "false":
					case "0":
						$output .= "화이트리스트가 비활성화되었습니다\n";
						$this->server->api->setProperty("white-list", false);
						break;
					default:
						$output .= "사용법: /whitelist <on|off|list|add|remove|reload> [닉네임]\n";
						break;
				}
				break;
			case "banip":
				$p = strtolower(array_shift($params));
				switch($p){
					case "pardon":
					case "remove":
						$ip = strtolower($params[0]);
						$this->bannedIPs->remove($ip);
						$this->bannedIPs->save();
						$output .= "IP \"$ip\"이 차단 목록에서 제외되었습니다.\n";
						break;
					case "add":
					case "ban":
						$ip = strtolower($params[0]);
						$player = $this->server->api->player->get($ip);
						if($player instanceof Player){
							$ip = $player->ip;
							$player->close("banned");
						}
						$this->bannedIPs->set($ip);
						$this->bannedIPs->save();
						$output .= "IP \"$ip\"이 차단 목록에 추가되었습니다.\n";
						break;
					case "reload":
						$this->bannedIPs = new Config(DATA_PATH."banned-ips.txt", CONFIG_LIST);
						break;
					case "list":
						$output .= "IP 차단 목록: ".implode(", ", $this->bannedIPs->getAll(true))."\n";
						break;
					default:
						$output .= "사용법: /banip <add|remove|list|reload> [IP|닉네임]\n";
						break;
				}
				break;
			case "ban":
				$p = strtolower(array_shift($params));
				switch($p){
					case "pardon":
					case "remove":
						$user = strtolower($params[0]);
						$this->banned->remove($user);
						$this->banned->save();
						$output .= "플레이어 \"$user\"님이 차단 목록에서 제외되었습니다.\n";
						break;
					case "add":
					case "ban":
						$user = strtolower($params[0]);
						$this->banned->set($user);
						$this->banned->save();
						$player = $this->server->api->player->get($user);
						if($player !== false){
							$player->close("당신은 차단되었습니다.");
						}
						if($issuer instanceof Player){
							$this->server->api->chat->broadcast($user."님이 ".$issuer->username."님에 의해 차단되었습니다.\n");
						}else{
							$this->server->api->chat->broadcast($user."님이 차단되었습니다.\n");
						}
						$this->kick($user, "Banned");
						$output .= "플레이어 \"$user\"차단 목록에 추가되었습니다.\n";
						break;
					case "reload":
						$this->banned = new Config(DATA_PATH."banned.txt", CONFIG_LIST);
						break;
					case "list":
						$output .= "차단 리스트: ".implode(", ", $this->banned->getAll(true))."\n";
						break;
					default:
						$output .= "사용법: /ban <add|remove|list|reload> [닉네임]\n";
						break;
				}
				break;
		}
		return $output;
	}
	
	public function ban($username){
		$this->commandHandler("ban", array("add", $username), "console", "");
	}
	
	public function pardon($username){
		$this->commandHandler("ban", array("pardon", $username), "console", "");
	}
	
	public function banIP($ip){
		$this->commandHandler("banip", array("add", $ip), "console", "");
	}
	
	public function pardonIP($ip){
		$this->commandHandler("banip", array("pardon", $ip), "console", "");
	}
	
	public function kick($username, $reason = "No Reason"){
		$this->commandHandler("kick", array($username, $reason), "console", "");
	}
	
	public function reload(){
		$this->commandHandler("ban", array("reload"), "console", "");
		$this->commandHandler("banip", array("reload"), "console", "");
		$this->commandHandler("whitelist", array("reload"), "console", "");
	}
	
	public function isIPBanned($ip){
		if($this->server->api->dhandle("api.ban.ip.check", $ip) === false){
			return true;
		}elseif($this->bannedIPs->exists($ip)){
			return true;
		}
		return false;
	}
	
	public function isBanned($username){
		$username = strtolower($username);
		if($this->server->api->dhandle("api.ban.check", $username) === false){
			return true;
		}elseif($this->banned->exists($username)){
			return true;
		}
		return false;	
	}
	
	public function inWhitelist($username){
		$username = strtolower($username);
		if($this->isOp($username)){
			return true;
		}elseif($this->server->api->dhandle("api.ban.whitelist.check", $username) === false){
			return true;
		}elseif($this->whitelist->exists($username)){
			return true;
		}
		return false;	
	}
}
