<?php
declare(strict_types=1);

namespace DontCraftItem;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\permission\DefaultPermissions;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class DontCraftItem extends PluginBase implements Listener{

	/** @var Config */
	protected Config $config;

	public array $db = [];

	protected function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		@mkdir($this->getDataFolder());
		$this->config = new Config($this->getDataFolder() . 'Config.yml', Config::YAML, [
			'ban-item' => []
		]);
		$this->db = $this->config->getAll();
		$this->getServer()->getCommandMap()->register('craftban', new AddCraftBanCommand($this));
	}

	public function onCraft(CraftItemEvent $event){
		$result = $event->getOutputs();
		$item = array_pop($result);
		if($item instanceof Item){
			$id = $item->getId();
			$damage = $item->getMeta();
			if(isset($this->db['ban-item'] [$id . ":" . $damage])){
				if($event->getPlayer()->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
					return;
				}
				$event->cancel();
				$event->getPlayer()->sendTip(TextFormat::RED . 'This Item was banned by admin!');
			}
		}
	}

	protected function onDisable() : void{
		$this->config->setAll($this->db);
		$this->config->save();
	}
}

class AddCraftBanCommand extends Command{

	protected $plugin;

	public function __construct(DontCraftItem $plugin){
		$this->plugin = $plugin;
		parent::__construct('craftban', 'Manage CraftBan', '/craftban [add | remove | list]');
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$sender instanceof Player)
			return true;
		if(!$sender->hasPermission(DefaultPermissions::ROOT_OPERATOR)){
			$sender->sendMessage(TextFormat::RED . 'You are not admin!');
			return true;
		}
		if(!isset($args[0])){
			$args[0] = 'x';
		}
		$inv = $sender->getInventory();
		switch($args[0]){
			case 'add':
				if($inv->getItemInHand()->getId() === 0){
					$sender->sendMessage(TextFormat::YELLOW . 'The id of the item must not be Air!');
					return true;
				}
				$this->plugin->db['ban-item'] [$inv->getItemInHand()->getId() . ":" . $inv->getItemInHand()->getMeta()] = true;
				$sender->sendMessage(TextFormat::YELLOW . 'Success');
				break;
			case 'remove':
				if($inv->getItemInHand()->getId() === 0){
					$sender->sendMessage(TextFormat::YELLOW . 'The id of the item must not be Air!');
					return true;
				}
				if(!isset($this->plugin->db['ban-item'] [$inv->getItemInHand()->getId() . ":" . $inv->getItemInHand()->getMeta()])){
					$sender->sendMessage(TextFormat::YELLOW . 'This item is not registered in db');
					return true;
				}
				unset($this->plugin->db['ban-item'] [$inv->getItemInHand()->getId() . ":" . $inv->getItemInHand()->getMeta()]);
				$sender->sendMessage(TextFormat::YELLOW . 'Success');
				break;
			case 'list':
				if(empty($this->plugin->db['ban-item'])){
					$sender->sendMessage(TextFormat::YELLOW . 'The database is empty.');
					break;
				}
				$sender->sendMessage(TextFormat::YELLOW . 'Ban list: ' . implode(", ", array_map(function(string $id) : string{
						return (string) $id;
					}, array_keys($this->plugin->db['ban-item']))));
				break;
			default:
				$sender->sendMessage(TextFormat::YELLOW . $this->getUsage());
		}
		return true;
	}
}
