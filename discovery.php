<?php
ini_set('display_errors', 'On'); error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
if(!class_exists('JSONplus') && file_exists(dirname(dirname(__FILE__)).'/JSONplus/JSONplus.php')){ require_once(dirname(dirname(__FILE__)).'/JSONplus/JSONplus.php'); }

class discovery{
	function __construct(){}
	function pulse_delay(){ return 59.890; }
	function known_ports(){
		return array(
			20 => 'ftp', 21 => 'ftp control', 990 => 'ftp+ssl',
			22 => 'ssh',
			23 => 'telnet',
			25 => 'smtp', 465 => 'smtp+tls/ssl',
			53 => 'dns',
			67 => 'dhcp server', 68 => 'dhcp client',
			80 => 'http', 8080 => 'http alternative',
			110 => 'pop3',
			143 => 'imap', 993 => 'imap+tls/ssl',
			389 => 'ldap',
			443 => 'https',
			3306 => 'mysql',
			5432 => 'postgresql'
		);
	}
	function ping($ip){
		$ping = `ping -c 1 -w 1 -n $ip`;
		preg_match_all("#time=([^\s]+(\sm?s)?)#", $ping, $b);
		return (isset($b[1][0]) ? $b[1][0] : FALSE);
	}
	// Ping by website domain name, IP address or Hostname
	function portping($domain, $port=80){
		$starttime = microtime(true);
		if(!($file = @fsockopen($domain, $port, $errno, $errstr, 10) ) ){ return -1; }
		$stoptime  = microtime(true);
		fclose($file);
		return number_format( ($stoptime - $starttime) * 1000 , 3 ).' ms';
	}
	function ifconfig_list(){
		$ifconfig = `ifconfig`;
		//print $ifconfig;

		preg_match_all("#inet\s([^\n]+)#", $ifconfig, $buffer);
		//print_r($buffer);
		$b = array();
		foreach($buffer[1] as $i=>$parm){
			preg_match_all("#([a-z]+)[\:]([^\s]+)#", $parm, $a);
			foreach($a[1] as $j=>$q){ $b[$i][$q] = $a[2][$j]; }
		}
		//print_r($b);
		return $b;
	}
	function ipize($p=array(), $merge=array()){
		$l=array();
		foreach($p as $i=>$o){
			$c = (isset($o['addr']) ? $o['addr'] : $o);
			$d = explode(".", $c);
			if(preg_match("#^([0-9]{1,3})[-]([0-9]{1,3})$#", $d[3], $x)){
				for($i=$x[1];$i<=$x[2];$i++){ $l[]=$d[0].".".$d[1].".".$d[2].".".$i; }
			}
			else{switch($d[3]){
				case '*': for($i=1;$i<255;$i++){ $l[]=$d[0].".".$d[1].".".$d[2].".".$i; } break;
				case '0': case '255': break;
				default: $l[]=$c;
			}}
		}
		if(is_array($merge) && count($merge)>=1){ $l = array_merge($l, discovery::ipize($merge)); }
		$l = array_unique($l);
		return $l;
	}
	function cycle($list, $recursive=FALSE, $hit=array(), $pulse=NULL, $ports=array()){
		/*fix*/ $pulse = ($pulse === NULL ? microtime(TRUE) : $pulse);
		/*fix*/ if(!is_array($hit)){ $hit = array(); }
		foreach($list as $i=>$ip){
			if(microtime(TRUE) >= ($pulse + self::pulse_delay() )){ /* cycle additional ping on found ip-addresses */
				$pulse = microtime(TRUE);
				print "\n----(".$pulse.")----";
				$hit = self::cycle(array_keys($hit), FALSE, $hit, $pulse, $ports);
				print "\n----(/".$pulse.")---\n";
				self::save_buffer($hit);
			}
			$ping = ping($ip);
			if($ping){
				print "\n".(!isset($hit[$ip]) ? "add\t" : NULL).$ip."\t".$ping."\t";
				$hit[$ip][(string) $pulse] = $ping;
				if(is_array($ports)){ $hit = self::cycle_with_ports(array($ip), $ports, $hit, $pulse); print "\n"; }
			}
			else{ print '.'; }
		}
		if(!($recursive === FALSE)){ $hit = self::cycle($list, $recursive, $hit, $pulse, $ports); }
		return $hit;
	}
	function cycle_with_ports($list, $recursive=FALSE, $hit=array(), $pulse=NULL){
		/*fix*/ $pulse = ($pulse === NULL ? microtime(TRUE) : $pulse);
		/*fix*/ if(!is_array($hit)){ $hit = array(); }
		/*fix*/ if(!is_bool($recursive)){ $ports = $recursive; $recursive = FALSE; } else { $ports = self::known_ports(); }
		foreach($list as $i=>$ip){
			foreach($ports as $port=>$desc){
				$pp = self::portping($ip, $port);
				if($pp != -1){ print "\n\t\t:".$port."\t".$pp." (".$desc.")\t"; $hit[$ip][(string) $pulse.':'.$port] = $pp; }
				else{ print '_'; }
			}
		}
		return $hit;
	}
	function save_buffer($buffer=array()){
		return file_put_contents(self::buffer_file(), (class_exists('JSONplus') ? JSONplus::encode($buffer) : json_encode($buffer)) );
	}
	function load_buffer(){
		return (class_exists('JSONplus') ? JSONplus::decode(file_get_contents(self::buffer_file())) : json_decode(file_get_contents(self::buffer_file()), TRUE));
	}
	function buffer_file(){ return dirname(__FILE__).'/discovery.json'; }
}
function ping($ip){ return discovery::ping($ip); }


if(TRUE /* $_SERVER['PATH_NAME'] == __FILE__ */){
	if(FALSE){ print '<pre>'; }
	$iflist = discovery::ifconfig_list();
	//print_r($iflist);
	$list = discovery::ipize($iflist, array('192.168.0.100-110','192.168.2.100-110','192.168.0.*','192.168.2.*'));
	//print_r($list);
	$hit = discovery::cycle($list, TRUE, discovery::load_buffer(), NULL, discovery::known_ports());
	//print_r($hit);
	if(FALSE){ print "</pre>"; }
	print "\n";
}
?>
