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


*/

class ConsoleAPI{
	private $loop, $server, $event, $help, $cmds, $alias;
	function __construct(){
		$this->help = array();
		$this->cmds = array();
		$this->alias = array();
		$this->server = ServerAPI::request();
		$this->last = microtime(true);
	}

	public function init(){
		$this->server->schedule(2, array($this, "handle"), array(), true);
		$this->loop = new ConsoleLoop();
		$this->register("help", "[page|command name]", array($this, "defaultCommands"));
		$this->register("status", "", array($this, "defaultCommands"));
		$this->register("difficulty", "<0|1|2|3>", array($this, "defaultCommands"));
		$this->register("stop", "", array($this, "defaultCommands"));
		$this->register("defaultgamemode", "<mode>", array($this, "defaultCommands"));
		$this->server->api->ban->cmdWhitelist("help");
	}

	function __destruct(){
		$this->server->deleteEvent($this->event);
		$this->loop->stop();
		$this->loop->notify();
		//$this->loop->join();
	}

	public function defaultCommands($cmd, $params, $issuer, $alias){
			$output = "";
				switch($cmd){
					case "defaultgamemode":
						$gms = array(
							"0" => SURVIVAL(서바이벌),
							"survival" => SURVIVAL(서바이벌),
							"s" => SURVIVAL(서바이벌),
							"1" => CREATIVE(크리에이티브),
							"creative" => CREATIVE(크리에이티브),
							"c" => CREATIVE(크리에이티브),
							"2" => ADVENTURE(어드벤처),
							"adventure" => ADVENTURE(어드벤처),
							"a" => ADVENTURE(어드벤처),
							"3" => VIEW(뷰:),
							"view" => VIEW,
							"viewer" => VIEW,
							"spectator" => VIEW,
							"v" => VIEW,
						);
						if(!isset($gms[strtolower($params[0])])){
							$output .= "사용법: /$cmd <모드>\n";
							break;
						}
						$this->server->api->setProperty("gamemode", $gms[strtolower($params[0])]);
						$output .= "이제 기본 게임모드는 ".strtoupper($this->server->getGamemode())."입니다.\n";
						break;
					case "status":
						if(!($issuer instanceof Player) and $issuer === "console"){
							$this->server->debugInfo(true);
						}
						$info = $this->server->debugInfo();
						$output .= "TPS: ".$info["tps"].", 메모리 사용량: ".$info["memory_usage"]." (최고 기록 ".$info["memory_peak_usage"].")\n";
						break;
					case "update-done":
						$this->server->api->setProperty("last-update", time());
						break;
					case "stop":
						$this->loop->stop = true;
						$output .= "서버를 멈추는 중...\n";
						$this->server->close();
						break;
					case "difficulty":
						$s = trim(array_shift($params));
						if($s === "" or (((int) $s) > 3 and ((int) $s) < 0)){
							$output .= "사용법: /difficulty <0|1|2|3>\n";
							break;
						}
						$this->server->api->setProperty("difficulty", (int) $s);
						$output .= "난이도가 ".$this->server->difficulty."으로 변경되었습니다.\n";
						break;
					case "?":
						if($issuer !== "console" and $issuer !== "rcon"){
							break;
						}
					case "help":
						if(isset($params[0]) and !is_numeric($params[0])){
							$c = trim(strtolower($params[0]));
							if(isset($this->help[$c]) or isset($this->alias[$c])){
								$c = isset($this->help[$c]) ? $c : $this->alias[$c];
								if($this->server->api->dhandle("console.command.".$c, array("cmd" => $c, "parameters" => array(), "issuer" => $issuer, "alias" => false)) === false
								or $this->server->api->dhandle("console.command", array("cmd" => $c, "parameters" => array(), "issuer" => $issuer, "alias" => false)) === false){
									break;
								}
								$output .= "사용법: /$c ".$this->help[$c]."\n";
								break;
							}
						}
						$cmds = array();
						foreach($this->help as $c => $h){
							if($this->server->api->dhandle("console.command.".$c, array("cmd" => $c, "parameters" => array(), "issuer" => $issuer, "alias" => false)) === false
							or $this->server->api->dhandle("console.command", array("cmd" => $c, "parameters" => array(), "issuer" => $issuer, "alias" => false)) === false){
								continue;
							}
							$cmds[$c] = $h;
						}
						
						$max = ceil(count($cmds) / 5);
						$page = (int) (isset($params[0]) ? min($max, max(1, intval($params[0]))):1);						
						$output .= "- 도움말 $max 페이지 중 $page 번째 페이지(/help <page>) -\n";
						$current = 1;
						foreach($cmds as $c => $h){
							$curpage = (int) ceil($current / 5);
							if($curpage === $page){
								$output .= "/$c ".$h."\n";
							}elseif($curpage > $page){
								break;
							}
							++$current;
						}
						break;
					default:
						$output .= "명령어가 존재하지 않습니다! /help 명령어를 사용해보세요.\n";
						break;
				}
		return $output;
	}

	public function alias($alias, $cmd){
		$this->alias[strtolower(trim($alias))] = trim($cmd);
		return true;
	}

	public function register($cmd, $help, $callback){
		if(!is_callable($callback)){
			return false;
		}
		$cmd = strtolower(trim($cmd));
		$this->cmds[$cmd] = $callback;
		$this->help[$cmd] = $help;
		ksort($this->help, SORT_NATURAL | SORT_FLAG_CASE);
	}
	
	public function run($line = "", $issuer = "console", $alias = false){
		if($line != ""){
			$end = strpos($line, " ");
			if($end === false){
				$end = strlen($line);
			}
			$cmd = strtolower(substr($line, 0, $end));
			$params = (string) substr($line, $end + 1);
			if(isset($this->alias[$cmd])){
				return $this->run($this->alias[$cmd] . ($params !== "" ? " " .$params:""), $issuer, $cmd);
			}
			if($issuer instanceof Player){
				console("[디버깅] \x1b[33m".$issuer->username."\x1b[0m 님이 명령어를 사용했습니다: ".ltrim("$alias ")."/$cmd ".$params, true, true, 2);
			}else{
				console("[DEBUG] \x1b[33m*".$issuer."\x1b[0m issued server command: ".ltrim("$alias ")."/$cmd ".$params, true, true, 2);
			}
			
			if(preg_match_all('#@([@a-z]{1,})#', $params, $matches, PREG_OFFSET_CAPTURE) > 0){
				$offsetshift = 0;
				foreach($matches[1] as $selector){
					if($selector[0]{0} === "@"){ //Escape!
						$params = substr_replace($params, $selector[0], $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
						--$offsetshift;
						continue;
					}
					switch(strtolower($selector[0])){
						case "u":
						case "player":
						case "username":
							$p = ($issuer instanceof Player) ? $issuer->username:$issuer;
							$params = substr_replace($params, $p, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
							$offsetshift -= strlen($selector[0]) - strlen($p) + 1;
							break;
						case "w":
						case "world":
							$p = ($issuer instanceof Player) ? $issuer->level->getName():$this->server->api->level->getDefault()->getName();
							$params = substr_replace($params, $p, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
							$offsetshift -= strlen($selector[0]) - strlen($p) + 1;
							break;
						case "a":
						case "all":
							$output = "";
							foreach($this->server->api->player->getAll() as $p){
								$output .= $this->run($cmd . " ". substr_replace($params, $p->username, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1), $issuer, $alias);
							}
							return $output;
						case "r":
						case "random":
							$l = array();
							foreach($this->server->api->player->getAll() as $p){
								if($p !== $issuer){
									$l[] = $p;
								}
							}
							if(count($l) === 0){
								return;
							}
							
							$p = $l[mt_rand(0, count($l) - 1)]->username;
							$params = substr_replace($params, $p, $selector[1] + $offsetshift - 1, strlen($selector[0]) + 1);
							$offsetshift -= strlen($selector[0]) - strlen($p) + 1;
							break;
					}
				}
			}
			$params = explode(" ", $params);
			if(count($params) === 1 and $params[0] === ""){
				$params = array();
			}
			
			if(($d1 = $this->server->api->dhandle("console.command.".$cmd, array("cmd" => $cmd, "parameters" => $params, "issuer" => $issuer, "alias" => $alias))) === false
			or ($d2 = $this->server->api->dhandle("console.command", array("cmd" => $cmd, "parameters" => $params, "issuer" => $issuer, "alias" => $alias))) === false){
				$output = "You don't have permissions to use this command.\n";
			}elseif($d1 !== true and $d2 !== true){
				if(isset($this->cmds[$cmd]) and is_callable($this->cmds[$cmd])){
					$output = @call_user_func($this->cmds[$cmd], $cmd, $params, $issuer, $alias);
				}elseif($this->server->api->dhandle("console.command.unknown", array("cmd" => $cmd, "params" => $params, "issuer" => $issuer, "alias" => $alias)) !== false){
					$output = $this->defaultCommands($cmd, $params, $issuer, $alias);
				}
			}else{
				$output = "";
			}
			if($output != "" and ($issuer instanceof Player)){
				$issuer->sendChat(trim($output));
			}
			return $output;
		}
	}

	public function handle($time){
		if($this->loop->line !== false){
			$line = trim($this->loop->line);
			$this->loop->line = false;
			$output = $this->run($line, "console");
			if($output != ""){
				$mes = explode("\n", trim($output));
				foreach($mes as $m){
					console("[CMD] ".$m);	
				}
			}
		}else{
			$this->loop->notify();
		}
	}

}

class ConsoleLoop extends Thread{
	public $line;
	public $stop;
	public $base;
	public $ev;
   public $fp;
	public function __construct(){
		$this->line = false;
		$this->stop = false;
		$this->start();
	}

	public function stop(){
		$this->stop = true;
	}

   private function readLine(){
      if( $this->fp ){
         $line = trim( fgets( $this->fp ) );
      } else {
         $line = trim( readline( "" ) );
         if( $line != "" ){
            readline_add_history( $line );
         }
      }

      return $line;
   }

	public function run(){
      if( ! extension_loaded( 'readline' ) ){
         $this->fp = fopen( "php://stdin", "r" );
      }

		while( $this->stop === false ) {
         $this->line = $this->readLine();
         $this->wait();
         $this->line = false;
		}

      if( ! $this->haveReadline ) {
         @fclose($fp);
      }
		exit(0);
	}
}
