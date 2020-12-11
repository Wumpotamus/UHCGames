<?php
declare(strict_types=1);

namespace uhcgames;

use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\block\tile\Chest;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;
use uhcgames\game\UHCGamesTask;
use muqsit\chunkloader\ChunkRegion;
use function array_shift;
use function explode;
use function in_array;
use function mt_rand;
use function shuffle;

class Loader extends PluginBase{
	/** @var Player[] */
	private $gamePlayers = [];
	/** @var Vector3[] */
	private $usedSpawns = [];
	/** @var UHCGamesTask */
	private $gameTask;
	/** @var string */
	private static $prefix;

	public function onEnable() : void{
		self::$prefix = str_replace("&", TextFormat::ESCAPE, $this->getConfig()->get("prefix"));
		$map = $this->getServer()->getWorldManager()->getDefaultWorld();
		if(!isset($this->getConfig()->get("worlds")[$map->getFolderName()])){
			$this->getLogger()->emergency("Map not found in configuration, shutting down!");
			$this->getServer()->shutdown();
		}else{
			if($map !== null){
				$map->setTime(7000);
				$map->stopTime();
				new EventListener($this);

				$this->gameTask = new UHCGamesTask($this, $map);
				$this->getScheduler()->scheduleRepeatingTask($this->gameTask, 20);
			}
		}
	}

	public static function getPrefix() : string{
		return self::$prefix;
	}

	public function getGameTask() : UHCGamesTask{
		return $this->gameTask;
	}

	public function addToGame(Player $player) : void{
		$this->gamePlayers[$player->getName()] = $player;
	}

	public function removeFromGame(Player $player) : void{
		unset($this->gamePlayers[$player->getName()]);
	}

	/**
	 * @return Player[]
	 */
	public function getGamePlayers() : array{
		return $this->gamePlayers;
	}

	public function isInGame(Player $player) : bool{
		return isset($this->gamePlayers[$player->getName()]);
	}

	public function removePlayerUsedSpawn(Player $player) : void{
		unset($this->usedSpawns[$player->getName()]);
	}

	/**
	 * @return Vector3[]
	 */
	public function getUsedSpawns() : array{
		return $this->usedSpawns;
	}

	public function isSpawnUsedByPlayer(Player $player) : bool{
		return isset($this->usedSpawns[$player->getName()]);
	}

	public function randomizeSpawn(Player $player, World $world) : void{
		$spawns = $this->getConfig()->get("worlds")[$world->getFolderName()]["spawnpoints"];
		shuffle($spawns);
		$locations = array_shift($spawns);
		ChunkRegion::onChunkGenerated($world, $locations[0] >> 4, $locations[2] >> 4, function() use($locations, $player, $world){
			if(!in_array($locations, $this->getUsedSpawns())){
				$player->teleport(new Vector3($locations[0], $locations[1], $locations[2]));
				$this->usedSpawns[$player->getName()] = $locations;
			}else{
				$this->randomizeSpawn($player, $world);
			}
		});
	}

	public function fillChest(Chest $chest) : void{
		$inventory = $chest->getInventory();
		$inventory->clearAll();
		foreach($this->getConfig()->get("items") as $item){
			if(mt_rand(1, 100) <= 50){
				$data = explode(":", $item);
				try{
					$itemString = VanillaItems::fromString($data[0]);
				}catch(\InvalidArgumentException $e){
					$itemString = VanillaBlocks::fromString($data[0])->asItem();
				}
				$count = 1;
				if(count($data) > 1){
					$count = mt_rand(1, (int) $data[1]);
				}
				$itemString->setCount($count);

				$rand = mt_rand(0, 26);
				$inventory->setItem($rand, $itemString);
			}
		}
	}

	public function onDisable() : void{
		$worldNames = array_keys((array) $this->getConfig()->get("worlds"));
		$this->getServer()->getConfigGroup()->setConfigString("level-name", $worldNames[array_rand($worldNames)]);
	}
}
