<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\skin\SkinData;
use pocketmine\network\mcpe\protocol\types\skin\SkinImage;
use function count;
use function json_decode;

class PlayerListPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::PLAYER_LIST_PACKET;

	public const TYPE_ADD = 0;
	public const TYPE_REMOVE = 1;

	public int $type;
	/** @var PlayerListEntry[] */
	public array $entries = [];

	/**
	 * @generate-create-func
	 * @param PlayerListEntry[] $entries
	 */
	private static function create(int $type, array $entries) : self{
		$result = new self;
		$result->type = $type;
		$result->entries = $entries;
		return $result;
	}

	/**
	 * @param PlayerListEntry[] $entries
	 */
	public static function add(array $entries) : self{
		return self::create(self::TYPE_ADD, $entries);
	}

	/**
	 * @param PlayerListEntry[] $entries
	 */
	public static function remove(array $entries) : self{
		return self::create(self::TYPE_REMOVE, $entries);
	}

	protected function decodePayload(PacketSerializer $in) : void{
		$this->type = $in->getByte();
		$count = $in->getUnsignedVarInt();
		for($i = 0; $i < $count; ++$i){
			$entry = new PlayerListEntry();

			if($this->type === self::TYPE_ADD){
				$entry->uuid = $in->getUUID();
				$entry->actorUniqueId = $in->getActorUniqueId();
				$entry->username = $in->getString();
				if($in->getProtocolId() >= ProtocolInfo::PROTOCOL_1_13_0){
					$entry->xboxUserId = $in->getString();
					$entry->platformChatId = $in->getString();
					$entry->buildPlatform = $in->getLInt();
					$entry->skinData = $in->getSkin();
					$entry->isTeacher = $in->getBool();
					$entry->isHost = $in->getBool();
				}else{
					$skinId = $in->getString();
					$skinData = $in->getString();
					$capeData = $in->getString();
					$geometryName = $in->getString();
					$geometryData = $in->getString();
					$entry->skinData = new SkinData(
						$skinId,
						"",
						null,
						SkinImage::fromLegacy($skinData),
						capeImage: SkinImage::fromLegacy($capeData),
						geometryData: $geometryData,
						geometryName: $geometryName,
					);
					$entry->xboxUserId = $in->getString();
					$entry->platformChatId = $in->getString();
				}
			}else{
				$entry->uuid = $in->getUUID();
			}

			$this->entries[$i] = $entry;
		}
		if($this->type === self::TYPE_ADD && $in->getProtocolId() >= ProtocolInfo::PROTOCOL_1_14_60){
			for($i = 0; $i < $count; ++$i){
				$this->entries[$i]->skinData->setVerified($in->getBool());
			}
		}
	}

	protected function encodePayload(PacketSerializer $out) : void{
		$out->putByte($this->type);
		$out->putUnsignedVarInt(count($this->entries));
		foreach($this->entries as $entry){
			if($this->type === self::TYPE_ADD){
				$out->putUUID($entry->uuid);
				$out->putActorUniqueId($entry->actorUniqueId);
				$out->putString($entry->username);
				if($out->getProtocolId() >= ProtocolInfo::PROTOCOL_1_13_0) {
					$out->putString($entry->xboxUserId);
					$out->putString($entry->platformChatId);
					$out->putLInt($entry->buildPlatform);
					$out->putSkin($entry->skinData);
					$out->putBool($entry->isTeacher);
					$out->putBool($entry->isHost);
				}else{
					$out->putString($entry->skinData->getSkinId());
					$out->putString($entry->skinData->getSkinImage()->getData());
					$out->putString($entry->skinData->getCapeImage()->getData());
					$out->putString(json_decode($entry->skinData->getResourcePatch(), true, flags: JSON_THROW_ON_ERROR)["geometry"]["default"]); // geometryName
					$out->putString($entry->skinData->getGeometryData());
					$out->putString($entry->xboxUserId);
					$out->putString($entry->platformChatId);
				}
			}else{
				$out->putUUID($entry->uuid);
			}
		}
		if($this->type === self::TYPE_ADD && $out->getProtocolId() >= ProtocolInfo::PROTOCOL_1_14_60){
			foreach($this->entries as $entry){
				$out->putBool($entry->skinData->isVerified());
			}
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handlePlayerList($this);
	}
}
