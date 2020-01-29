<?php

/*
*   Bounty Pocketmine Plugin
*   Copyright (C) 2019-2020 JaxkDev
*
*   This program is free software: you can redistribute it and/or modify
*   it under the terms of the GNU General Public License as published by
*   the Free Software Foundation, either version 3 of the License, or
*   any later version.
*
*   This program is distributed in the hope that it will be useful,
*   but WITHOUT ANY WARRANTY; without even the implied warranty of
*   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*   GNU General Public License for more details.
*
*   You should have received a copy of the GNU General Public License
*   along with this program.  If not, see <https://www.gnu.org/licenses/>.
*
*   Twitter :: @JaxkDev
*   Discord :: JaxkDev#8860
*   Email   :: JaxkDev@gmail.com
*/

declare(strict_types=1);

namespace Jack\Bounty;

use pocketmine\event\Listener;
use pocketmine\utils\TextFormat as C;

use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\player\{PlayerJoinEvent,PlayerDeathEvent,PlayerQuitEvent};;

use Jack\Bounty\Events\{BountyClaimEvent,BountyAddEvent,BountyCreateEvent,BountyRemoveEvent};;


class EventListener implements Listener{

    public $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function handleCommand(CommandSender $sender, Command $cmd, string $label, array $args): bool{
        if($cmd->getName() == "bounty"){
            if(!isset($args[0])){
                return false;
            }
            if(!$sender instanceof Player){
                $sender->sendMessage(C::RED."Commands can only be run in-game");
                return true;
            }
            switch($args[0]){
                case 'list':
                    if(!isset($args[1]) || $args[1] == '1'){
                        if(count($this->plugin->data['bounty']) == 0){
                            $sender->sendMessage(C::RED."Nobody has a bounty yet.");
                            return true;
                        } else {
                            $tmp = null;
                            if(count($this->plugin->data['bounty']) >= 5){
                                $tmp = array_slice($this->plugin->data['bounty'], 0, 5, true);
                            } else {
                                $tmp = array_slice($this->plugin->data['bounty'], 0, count($this->plugin->data['bounty']), true);
                            }
                            $data = C::GREEN.'Page '.C::RED."1".C::GOLD."/".C::RED.ceil(count($this->plugin->data['bounty'])/5)."\n";
                            foreach($tmp as $user => $price){
                                $data = $data.C::GOLD.$user." -> ".C::GREEN."$".$price."\n";
                            }
                            $sender->sendMessage($data);
                            return true;
                        }
                    } else {
                        if(is_nan(intval($args[1]))){
                            $sender->sendMessage(C::RED."Not a valid page number.");
                            return true;
                        } else {
                            if(count($this->plugin->data['bounty']) == 0){
                                $sender->sendMessage(C::RED."Nobody has a bounty yet.");
                                return true;
                            } else {
                                if(intval($args[1]) > ceil(count($this->plugin->data['bounty'])/5)){
                                    $sender->sendMessage(C::RED."Theres only ".ceil(count($this->plugin->data['bounty'])/5). " Pages.");
                                    return true;
                                } else {
                                    $tmp = null;
                                    if(count($this->plugin->data['bounty']) >= intval($args[1])*5){
                                        $tmp = array_slice($this->plugin->data['bounty'], (intval($args[1])*5)-5, (intval($args[1])*5), true);
                                    } else {
                                        $tmp = array_slice($this->plugin->data['bounty'], (intval($args[1])*5)-5, count($this->plugin->data['bounty']), true);
                                    }
                                    $data = C::GREEN.'Page '.C::RED.$args[1].C::GOLD."/".C::RED.ceil(count($this->plugin->data['bounty'])/5)."\n";
                                    foreach($tmp as $user => $price){
                                        $data = $data.C::GOLD.$user.C::RED." -> ".C::GREEN."$".$price."\n";
                                    }
                                    $sender->sendMessage($data);
                                    return true;
                                }
                            }
                        }
                    }
                case 'credits':
                    $sender->sendMessage(C::GOLD."=== Credits ===");
                    $sender->sendMessage(C::GREEN."Developer: ".C::RED."Jackthehack21");
                    return true;
                case 'help':
                    $sender->sendMessage(C::GREEN."-- Bounty Help: --");
                    $sender->sendMessage(C::GOLD."/bounty new <playername> <amount>");
                    if($sender->hasPermission("bounty.rem")) $sender->sendMessage(C::GOLD."/bounty rem <playername>");
                    $sender->sendMessage(C::GOLD."/bounty list <page>");
                    if($this->plugin->config["leaderboard"] === true) $sender->sendMessage(C::GOLD."/bounty leaderboard");
                    $sender->sendMessage(C::GOLD."/bounty help");
                    $sender->sendMessage(C::GOLD."/bounty credits");
                    break;
                case "add":
                case "new":
                    if(!$sender->hasPermission("bounty.new")){
                        $msg = $this->plugin->config["bounty_noperms"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
                        break;
                    }
                    if(!isset($args[1]) || !isset($args[2])){
                        $sender->sendMessage(C::RED."Usage: /bounty new <playername> <amount>");
                        break;
                    }
                    $noob = $this->plugin->getServer()->getOfflinePlayer($args[1]);
                    if(!is_numeric($noob->getFirstPlayed())){
                        $msg = $this->plugin->config["bounty_new_notfound"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
                        return true;
                    }
                    if($this->plugin->config["bounty_multiple"] === false && isset($this->plugin->data['bounty'][strtolower($noob->getName())])){
                        $msg = $this->plugin->config["bounty_new_already"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
                        return true;
                    }
                    $mon = $this->plugin->economy->myMoney($sender->getName());

                    $amount = intval($args[2]);

                    if(is_nan($amount)){
                        $sender->sendMessage(C::RED."Usage: /bounty new <playername> <AMOUNT>");
                        return true;
                    }

                    if($this->plugin->config["bounty_limit_enforced"] === true){
                        $min = $this->plugin->config["bounty_min"];
                        $max = $this->plugin->config["bounty_max"];
                        $msg = str_replace("{MAX}", $max, str_replace("{MIN}", $min, $this->colour($this->plugin->config["bounty_new_fundlimit"])));
                        if($amount > $max || $amount < $min){
                            if($msg !== "") $sender->sendMessage($msg);
                            return true;
                        }
                    }

                    if(isset($this->plugin->data["bounty"][strtolower($noob->getName())])){
                        if($this->plugin->config["bounty_multiple"] === true){
                            //Add to existing:
                            //events:
                            $event = new BountyAddEvent($this->plugin, $sender, $noob, $amount);
                            $this->plugin->getServer()->getPluginManager()->callEvent($event);
                            if($event->isCancelled()){
                                $msg = $this->plugin->config["bounty_multiple_cancelled"];
                                if($msg !== "") $sender->sendMessage($this->colour($msg));
                                return true;
                            }

                            $amount = $event->getAmount();

                            if($amount > $mon){
                                $msg = $this->plugin->config["bounty_multiple_funds"];
                                if($msg !== "") $sender->sendMessage($this->colour($msg));
                                return true;
                            }
                            $this->plugin->economy->reduceMoney($sender->getName(), $amount);
                            $this->plugin->data['bounty'][strtolower($noob->getName())] = $this->plugin->data['bounty'][strtolower($noob->getName())]+$amount;
                            $this->plugin->save();
                            if($this->plugin->config['bounty_multiple_success'] !== "") $sender->sendMessage($this->colour($this->plugin->config['bounty_multiple_success']));
                            foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
                                $msg = str_replace("{TOTAL}", $this->plugin->data['bounty'][strtolower($noob->getName())], str_replace('{SENDER}', $sender->getName(), str_replace('{AMOUNT}', $amount,str_replace('{PLAYER}',$noob->getName(),$this->colour($this->plugin->config["bounty_multiple_broadcast"])))));
                                if($msg !== "") $player->sendMessage($msg);
                            }
                            return true;
                        }  else {
                            //already has bounty, and multiple disabled.
                            $msg = $this->plugin->config["bounty_new_already"];
                            if($msg !== "") $sender->sendMessage($this->colour($msg));
                            return true;
                        }
                    }

                    //Create new:

                    //events:
                    $event = new BountyCreateEvent($this->plugin, $sender, $noob, $amount);
			        $event->call();
			        if($event->isCancelled()){
                        $msg = $this->plugin->config["bounty_new_cancelled"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
				        return true;
                    }

                    $amount = $event->getAmount();

                    if($amount > $mon){
                        $msg = $this->plugin->config["bounty_new_funds"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
                        return true;
                    }
                    $this->plugin->economy->reduceMoney($sender->getName(), $amount);
                    $this->plugin->data['bounty'][strtolower($noob->getName())] = $amount;
                    $this->plugin->save();
                    if($this->plugin->config['bounty_new_success'] !== "") $sender->sendMessage($this->colour($this->plugin->config['bounty_new_success']));
                    foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
                        $msg = str_replace('{SENDER}', $sender->getName(), str_replace('{AMOUNT}', $amount,str_replace('{PLAYER}',$noob->getName(),$this->colour($this->plugin->config["bounty_new_broadcast"]))));
                        if($msg !== "") $player->sendMessage($msg);
                    }
                    return true;

                case "rem":
                case "remove":
                    if(!$sender->hasPermission("bounty.rem")){
                        $msg = $this->plugin->config["bounty_noperms"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
                        break;
                    }
                    if(!isset($args[1])){
                        $sender->sendMessage(C::RED."Usage: /bounty rem <playername>");
                        break;
                    }
                    if(count($this->plugin->data['bounty']) == 0 || !is_int($this->plugin->data["bounty"][strtolower($args[1])])){
                        $msg = $this->plugin->config["bounty_rem_notfound"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
                        break;
                    }

                    //events:
                    $event = new BountyRemoveEvent($this->plugin, $sender, $args[1], $this->plugin->data["bounty"][strtolower($args[1])]);
			        $event->call();
			        if($event->isCancelled()){
                        $msg = $this->plugin->config["bounty_rem_cancelled"];
                        if($msg !== "") $sender->sendMessage($this->colour($msg));
				        return true;
                    }

                    foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
                        $msg = str_replace('{SENDER}', $sender->getName(),str_replace('{PLAYER}',$args[1],$this->colour($this->plugin->config["bounty_rem_broadcast"])));
                        if($msg !== "") $player->sendMessage($msg);
                    }

                    unset($this->plugin->data["bounty"][strtolower($args[1])]);
                    $this->plugin->save();
                    if($this->plugin->config["bounty_rem_success"] !== "") $sender->sendMessage($this->colour($this->plugin->config["bounty_rem_success"]));
                    break;

                case 'leaderboard':
                case 'lb':
                    if($this->plugin->config["leaderboard"] !== true) return true;
                    if(count($this->plugin->data['bounty']) == 0){
                        $sender->sendMessage("Nobody has a bounty.");
                        return true;
                    }
                    switch($this->plugin->config["leaderboard_format"]){
                        case "form":
                            $form = new Form();
                            $form->data = [
                                "type" => "custom_form",
                                "title" => C::AQUA.C::BOLD." -- Bounty Leaderboard --",
                                "content" => []
                            ];
                            $form->data["content"][] = ["type" => "label", "text" => C::GOLD.C::BOLD."Name : Amount"];
                            $lb = $this->plugin->data['bounty'];
                            asort($lb);
                            $lb = array_reverse($lb);
                            $count = 1;
                            foreach($lb as $name => $amount){
                                $form->data["content"][] = ["type" => "label", "text" => C::GREEN.$count.". ".C::AQUA.$name." : $".$amount];
                                $count += 1;
                            }
                            $sender->sendForm($form);
                            break;
                        case "text":
                        case "chat":
                            $prefix = C::BOLD.C::GOLD."-- Bounty Leaderboard --";
                            $msg = C::AQUA."";
                            $lb = $this->plugin->data['bounty'];
                            asort($lb);
                            $lb = array_reverse($lb);
                            $count = 1;
                            foreach($lb as $name => $amount){
                                $msg = $msg.C::GREEN.$count.". ".C::AQUA.$name." : $".$amount."\n";
                                $count += 1;
                            }
                            $sender->sendMessage($prefix);
                            $sender->sendMessage($msg);
                            break;
                        default:
                            $sender->sendMessage("Not a valid option in config.yml, try using 'form' option.");
                    }
                    return true;
                default:
                    $sender->sendMessage(C::RED."Invalid Command, Try /bounty help");
                    break;
            }
            return true;
        }
    }

    public function onSpawn(PlayerJoinEvent $event){
        $player = $event->getPlayer();
        if(isset($this->plugin->data["bounty"][strtolower($player->getName())])){
            $msg = str_replace("{AMOUNT}", $this->plugin->data["bounty"][strtolower($player->getName())], str_replace("{PLAYER}", $player->getName(), $this->plugin->config["bounty_player_join"]));
            if($msg === "") return;
            foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
                $player->sendMessage($this->colour($msg));
            }
        }
    }

    public function onQuit(PlayerQuitEvent $event){
        $player = $event->getPlayer();
        if(isset($this->plugin->data["bounty"][strtolower($player->getName())])){
            $msg = str_replace("{AMOUNT}", $this->plugin->data["bounty"][strtolower($player->getName())], str_replace("{PLAYER}", $player->getName(), $this->plugin->config["bounty_player_quit"]));
            if($msg === "") return;
            foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
                $player->sendMessage($this->colour($msg));
            }
        }
    }

    public function onDeath(PlayerDeathEvent $event){
		//TODO test projectile owning entity #15
		$killer = null;
        $cause = $event->getEntity()->getLastDamageCause();
		if ($cause === null) return false; //*shrug* - issues have arised when external plugins create deaths.
        if ($cause->getCause() != 1) return false; //not killed by entity
        if (!$cause instanceof EntityDamageByEntityEvent) return false; //double check of above check.
        if ($cause->getDamager() instanceof Player) {
            $killer = $cause->getDamager();
		}
		if ($cause->getDamager() instanceof Entity){
			if ($cause->getDamager()->getOwningEntity() instanceof Player){
				$killer = $cause->getDamager()->getOwningEntity();
			}
		}
		if ($killer !== null){
            if(isset($this->plugin->data["bounty"][strtolower($event->getPlayer()->getName())])){
                //events:
                $ev = new BountyClaimEvent($this->plugin, $killer, $event->getPlayer(), $this->plugin->data["bounty"][strtolower($event->getPlayer()->getName())]);
			    $this->plugin->getServer()->getPluginManager()->callEvent($ev);
		        if($ev->isCancelled()){
                    if($this->plugin->config["bounty_claim_cancelled"] !== "") $killer->sendMessage($this->colour($this->plugin->config["bounty_claim_cancelled"]));
				    return true;
                }

                if($this->plugin->config["bounty_claim_success"] !== "") $killer->sendMessage($this->colour(str_replace('{AMOUNT}', $this->plugin->data["bounty"][strtolower($event->getPlayer()->getName())],str_replace('{PLAYER}',$event->getPlayer()->getLowerCaseName(),$this->plugin->config["bounty_claim_success"]))));
                foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
                    $msg = str_replace("{PLAYER}", $event->getPlayer()->getName(), str_replace("{SENDER}", $killer->getName(), str_replace("{AMOUNT}", $this->plugin->data["bounty"][strtolower($event->getPlayer()->getName())] , $this->plugin->config["bounty_claim_broadcast"])));
                    if($msg !== "") $player->sendMessage($this->colour($msg));
                }
                $this->plugin->economy->addMoney($killer->getName(), $this->plugin->data["bounty"][strtolower($event->getPlayer()->getName())]);
                unset($this->plugin->data["bounty"][strtolower($event->getPlayer()->getName())]);
                $this->plugin->save();
            }
        }
        return false;
    }

    public function colour(string $msg) : string {
        $colour = array("{BLACK}","{DARK_BLUE}","{DARK_GREEN}","{DARK_AQUA}","{DARK_RED}","{DARK_PURPLE}","{GOLD}","{GRAY}","{DARK_GRAY}","{BLUE}","{GREEN}","{AQUA}","{RED}","{LIGHT_PURPLE}","{YELLOW}","{WHITE}","{OBFUSCATED}","{BOLD}","{STRIKETHROUGH}","{UNDERLINE}","{ITALIC}","{RESET}");
        $keys = array(C::BLACK, C::DARK_BLUE, C::DARK_GREEN, C::DARK_AQUA, C::DARK_RED, C::DARK_PURPLE, C::GOLD, C::GRAY, C::DARK_GRAY, C::BLUE, C::GREEN, C::AQUA, C::RED, C::LIGHT_PURPLE, C::YELLOW, C::WHITE, C::OBFUSCATED, C::BOLD, C::STRIKETHROUGH, C::UNDERLINE, C::ITALIC, C::RESET);
        return str_replace(
            $colour,
            $keys,
            $msg
        );
    }
}
