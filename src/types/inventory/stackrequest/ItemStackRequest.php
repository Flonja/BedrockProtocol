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

namespace pocketmine\network\mcpe\protocol\types\inventory\stackrequest;

use pocketmine\network\mcpe\protocol\PacketDecodeException;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\utils\BinaryDataException;
use function count;

final class ItemStackRequest{
	/**
	 * @param ItemStackRequestAction[] $actions
	 * @param string[]                 $filterStrings
	 * @phpstan-param list<string> $filterStrings
	 */
	public function __construct(
		private int $requestId,
		private array $actions,
		private array $filterStrings,
		private int $filterStringCause
	){}

	public function getRequestId() : int{ return $this->requestId; }

	/** @return ItemStackRequestAction[] */
	public function getActions() : array{ return $this->actions; }

	/**
	 * @return string[]
	 * @phpstan-return list<string>
	 */
	public function getFilterStrings() : array{ return $this->filterStrings; }

	public function getFilterStringCause() : int{ return $this->filterStringCause; }

	/**
	 * @throws BinaryDataException
	 * @throws PacketDecodeException
	 */
	private static function readAction(PacketSerializer $in, int $typeId) : ItemStackRequestAction{
		return match($typeId){
			TakeStackRequestAction::ID => TakeStackRequestAction::read($in),
			PlaceStackRequestAction::ID => PlaceStackRequestAction::read($in),
			SwapStackRequestAction::ID => SwapStackRequestAction::read($in),
			DropStackRequestAction::ID => DropStackRequestAction::read($in),
			DestroyStackRequestAction::ID => DestroyStackRequestAction::read($in),
			CraftingConsumeInputStackRequestAction::ID => CraftingConsumeInputStackRequestAction::read($in),
			CraftingMarkSecondaryResultStackRequestAction::ID => CraftingMarkSecondaryResultStackRequestAction::read($in),
			PlaceIntoBundleStackRequestAction::ID => PlaceIntoBundleStackRequestAction::read($in),
			TakeFromBundleStackRequestAction::ID => TakeFromBundleStackRequestAction::read($in),
			LabTableCombineStackRequestAction::ID => LabTableCombineStackRequestAction::read($in),
			BeaconPaymentStackRequestAction::ID => BeaconPaymentStackRequestAction::read($in),
			MineBlockStackRequestAction::ID => MineBlockStackRequestAction::read($in),
			CraftRecipeStackRequestAction::ID => CraftRecipeStackRequestAction::read($in),
			CraftRecipeAutoStackRequestAction::ID => CraftRecipeAutoStackRequestAction::read($in),
			CreativeCreateStackRequestAction::ID => CreativeCreateStackRequestAction::read($in),
			CraftRecipeOptionalStackRequestAction::ID => CraftRecipeOptionalStackRequestAction::read($in),
			GrindstoneStackRequestAction::ID => GrindstoneStackRequestAction::read($in),
			LoomStackRequestAction::ID => LoomStackRequestAction::read($in),
			DeprecatedCraftingNonImplementedStackRequestAction::ID => DeprecatedCraftingNonImplementedStackRequestAction::read($in),
			DeprecatedCraftingResultsStackRequestAction::ID => DeprecatedCraftingResultsStackRequestAction::read($in),
			default => throw new PacketDecodeException("Unhandled item stack request action type $typeId"),
		};
	}

	public static function read(PacketSerializer $in) : self{
		$requestId = $in->readGenericTypeNetworkId();
		$actions = [];
		for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
			$typeId = $in->getByte();
			if($typeId >= ItemStackRequestActionType::PLACE_INTO_BUNDLE && $in->getProtocolId() < ProtocolInfo::PROTOCOL_1_18_10){
				$typeId += ItemStackRequestActionType::LAB_TABLE_COMBINE - ItemStackRequestActionType::PLACE_INTO_BUNDLE;
			}
			$actions[] = self::readAction($in, $typeId);
		}
		$filterStrings = [];
		if($in->getProtocolId() >= ProtocolInfo::PROTOCOL_1_16_200){
			for($i = 0, $len = $in->getUnsignedVarInt(); $i < $len; ++$i){
				$filterStrings[] = $in->getString();
			}
		}
		if($in->getProtocolId() >= ProtocolInfo::PROTOCOL_1_19_50){
			$filterStringCause = $in->getLInt();
		}else{
			$filterStringCause = 0;
		}
		return new self($requestId, $actions, $filterStrings, $filterStringCause);
	}

	public function write(PacketSerializer $out) : void{
		$out->writeGenericTypeNetworkId($this->requestId);
		$out->putUnsignedVarInt(count($this->actions));
		foreach($this->actions as $action){
			$typeId = $action->getTypeId();
			if($typeId >= ItemStackRequestActionType::PLACE_INTO_BUNDLE && $out->getProtocolId() < ProtocolInfo::PROTOCOL_1_18_10){
				$typeId -= ItemStackRequestActionType::LAB_TABLE_COMBINE - ItemStackRequestActionType::PLACE_INTO_BUNDLE;
			}
			$out->putByte($typeId);
			$action->write($out);
		}
		if($out->getProtocolId() >= ProtocolInfo::PROTOCOL_1_16_200){
			$out->putUnsignedVarInt(count($this->filterStrings));
			foreach($this->filterStrings as $string){
				$out->putString($string);
			}
		}
		if($out->getProtocolId() >= ProtocolInfo::PROTOCOL_1_19_50){
			$out->putLInt($this->filterStringCause);
		}
	}
}
