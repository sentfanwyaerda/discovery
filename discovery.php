<?php
ini_set('display_errors', 'On'); error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
if(!class_exists('JSONplus') && file_exists(dirname(dirname(__FILE__)).'/JSONplus/JSONplus.php')){ require_once(dirname(dirname(__FILE__)).'/JSONplus/JSONplus.php'); }

class discovery{
	function __construct(){}
	function ping($ip){
		$ping = `ping -c 1 -w 1 -n $ip`;
		preg_match_all("#time=([^\s]+(\sm?s)?)#", $ping, $b);
		return (isset($b[1][0]) ? $b[1][0] : FALSE);
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
	function cycle($list, $recursive=FALSE, $hit=array(), $pulse=NULL){
		/*fix*/ $pulse = ($pulse === NULL ? microtime(TRUE) : $pulse);
		/*fix*/ if(!is_array($hit)){ $hit = array(); }
		foreach($list as $i=>$ip){
			if(microtime(TRUE) >= ($pulse + 59.501)){ /* cycle additional ping on found ip-addresses */
				$pulse = microtime(TRUE);
				print "\n----(".$pulse.")----";
				$hit = self::cycle(array_keys($hit), FALSE, $hit, $pulse);
				print "\n----(/".$pulse.")---\n";
				file_put_contents(dirname(__FILE__).'/discovery.json', (class_exists('JSONplus') ? JSONplus::encode($hit) : json_encode($hit)) );
			}
			$ping = ping($ip);
			if($ping){ print "\n".(isset($hit[$ip]) ? "\t" : NULL).$ip."\t".$ping."\t"; $hit[$ip][$pulse] = $ping; }
			else{ print '.'; }
		}
		if($recursive !== FALSE){ $hit = self::cycle($list, $recursive, $hit, $pulse); }
		return $hit;
	}
}
function ping($ip){ return discovery::ping($ip); }


if(TRUE /* $_SERVER['PATH_NAME'] == __FILE__ */){
	print '<pre>';
	$iflist = discovery::ifconfig_list();
	//print_r($iflist);
	$list = discovery::ipize($iflist, array(/*'192.168.0.100-110','192.168.2.100-110',*/'192.168.0.*','192.168.2.*'));
	//print_r($list);
	$hit = discovery::cycle($list, TRUE);
	//print_r($hit);
	print "\n</pre>\n";
}
?>
