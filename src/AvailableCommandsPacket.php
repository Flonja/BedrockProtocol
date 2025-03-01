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
use pocketmine\network\mcpe\protocol\types\command\CommandData;
use pocketmine\network\mcpe\protocol\types\command\CommandEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandEnumConstraint;
use pocketmine\network\mcpe\protocol\types\command\CommandParameter;
use pocketmine\utils\BinaryDataException;
use function array_search;
use function count;
use function dechex;

class AvailableCommandsPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::AVAILABLE_COMMANDS_PACKET;

	/**
	 * This flag is set on all types EXCEPT the POSTFIX type. Not completely sure what this is for, but it is required
	 * for the argtype to work correctly. VALID seems as good a name as any.
	 */
	public const ARG_FLAG_VALID = 0x100000;

	/**
	 * Basic parameter types. These must be combined with the ARG_FLAG_VALID constant.
	 * ARG_FLAG_VALID | (type const)
	 */
	public const ARG_TYPE_INT = 0x01;
	public const ARG_TYPE_FLOAT = 0x03;
	public const ARG_TYPE_VALUE = 0x04;
	public const ARG_TYPE_WILDCARD_INT = 0x05;
	public const ARG_TYPE_OPERATOR = 0x06;
	public const ARG_TYPE_COMPARE_OPERATOR = 0x07;
	public const ARG_TYPE_TARGET = 0x08;

	public const ARG_TYPE_WILDCARD_TARGET = 0x0a;

	public const ARG_TYPE_FILEPATH = 0x11;

	public const ARG_TYPE_FULL_INTEGER_RANGE = 0x17;

	public const ARG_TYPE_EQUIPMENT_SLOT = 0x26;
	public const ARG_TYPE_STRING = 0x27;

	public const ARG_TYPE_INT_POSITION = 0x2f;
	public const ARG_TYPE_POSITION = 0x30;

	public const ARG_TYPE_MESSAGE = 0x33;

	public const ARG_TYPE_RAWTEXT = 0x35;

	public const ARG_TYPE_JSON = 0x39;

	public const ARG_TYPE_BLOCK_STATES = 0x43;

	public const ARG_TYPE_COMMAND = 0x46;

	/**
	 * Enums are a little different: they are composed as follows:
	 * ARG_FLAG_ENUM | ARG_FLAG_VALID | (enum index)
	 */
	public const ARG_FLAG_ENUM = 0x200000;

	/** This is used for /xp <level: int>L. It can only be applied to integer parameters. */
	public const ARG_FLAG_POSTFIX = 0x1000000;

	public const HARDCODED_ENUM_NAMES = [
		"CommandName" => true
	];

	/**
	 * @var CommandData[]
	 * List of command data, including name, description, alias indexes and parameters.
	 */
	public array $commandData = [];

	/**
	 * @var CommandEnum[]
	 * List of enums which aren't directly referenced by any vanilla command.
	 * This is used for the `CommandName` enum, which is a magic enum used by the `command` argument type.
	 */
	public array $hardcodedEnums = [];

	/**
	 * @var CommandEnum[]
	 * List of dynamic command enums, also referred to as "soft" enums. These can by dynamically updated mid-game
	 * without resending this packet.
	 */
	public array $softEnums = [];

	/**
	 * @var CommandEnumConstraint[]
	 * List of constraints for enum members. Used to constrain gamerules that can bechanged in nocheats mode and more.
	 */
	public array $enumConstraints = [];

	/**
	 * @generate-create-func
	 * @param CommandData[]           $commandData
	 * @param CommandEnum[]           $hardcodedEnums
	 * @param CommandEnum[]           $softEnums
	 * @param CommandEnumConstraint[] $enumConstraints
	 */
	public static function create(array $commandData, array $hardcodedEnums, array $softEnums, array $enumConstraints) : self{
		$result = new self;
		$result->commandData = $commandData;
		$result->hardcodedEnums = $hardcodedEnums;
		$result->softEnums = $softEnums;
		$result->enumConstraints = $enumConstraints;
		return $result;
	}

	public static function convertArg(int $protocolId, int $type) : int{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_19_0){
			return $type;
		}

		if($protocolId >= ProtocolInfo::PROTOCOL_1_18_30){
			return match($type) {
				self::ARG_TYPE_TARGET => 0x07,
				self::ARG_TYPE_WILDCARD_TARGET => 0x09,
				self::ARG_TYPE_FILEPATH => 0x10,
				self::ARG_TYPE_EQUIPMENT_SLOT => 0x25,
				self::ARG_TYPE_STRING => 0x26,
				self::ARG_TYPE_INT_POSITION => 0x2e,
				self::ARG_TYPE_POSITION => 0x2f,
				self::ARG_TYPE_MESSAGE => 0x32,
				self::ARG_TYPE_RAWTEXT => 0x34,
				self::ARG_TYPE_JSON => 0x38,
				self::ARG_TYPE_COMMAND => 0x45,
				default => $type,
			};
		}

		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_210){
			return match ($type) {
				self::ARG_TYPE_TARGET => 0x07,
				self::ARG_TYPE_WILDCARD_TARGET => 0x08,
				self::ARG_TYPE_FILEPATH => 0x10,
				self::ARG_TYPE_STRING => 0x20,
				self::ARG_TYPE_POSITION => 0x28,
				self::ARG_TYPE_MESSAGE => 0x2c,
				self::ARG_TYPE_RAWTEXT => 0x2e,
				self::ARG_TYPE_JSON => 0x32,
				self::ARG_TYPE_COMMAND => 0x3f,
				default => $type,
			};
		}

		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_100){
			return match ($type) {
				self::ARG_TYPE_FLOAT => 0x02,
				self::ARG_TYPE_VALUE => 0x03,
				self::ARG_TYPE_WILDCARD_INT => 0x04,
				self::ARG_TYPE_OPERATOR => 0x05,
				self::ARG_TYPE_TARGET => 0x06,
				self::ARG_TYPE_WILDCARD_TARGET => 0x07,
				self::ARG_TYPE_FILEPATH => 0x0f,
				self::ARG_TYPE_STRING => 0x1f,
				self::ARG_TYPE_POSITION => 0x28,
				self::ARG_TYPE_MESSAGE => 0x2b,
				self::ARG_TYPE_RAWTEXT => 0x2d,
				self::ARG_TYPE_JSON => 0x31,
				self::ARG_TYPE_COMMAND => 0x38,
				default => $type,
			};
		}

		if($protocolId >= ProtocolInfo::PROTOCOL_1_13_0){
			return match ($type) {
				self::ARG_TYPE_FLOAT => 0x02,
				self::ARG_TYPE_VALUE => 0x03,
				self::ARG_TYPE_WILDCARD_INT => 0x04,
				self::ARG_TYPE_OPERATOR => 0x05,
				self::ARG_TYPE_TARGET => 0x06,
				self::ARG_TYPE_WILDCARD_TARGET => 0x07,
				self::ARG_TYPE_FILEPATH => 0x0e,
				self::ARG_TYPE_STRING => 0x1d,
				self::ARG_TYPE_POSITION => 0x26,
				self::ARG_TYPE_MESSAGE => 0x29,
				self::ARG_TYPE_RAWTEXT => 0x2b,
				self::ARG_TYPE_JSON => 0x2f,
				self::ARG_TYPE_COMMAND => 0x36,
				default => $type,
			};
		}

		return match ($type) {
			self::ARG_TYPE_FLOAT => 0x02,
			self::ARG_TYPE_VALUE => 0x03,
			self::ARG_TYPE_WILDCARD_INT => 0x04,
			self::ARG_TYPE_OPERATOR => 0x05,
			self::ARG_TYPE_TARGET => 0x06,
			self::ARG_TYPE_WILDCARD_TARGET => 0x07,
			self::ARG_TYPE_FILEPATH => 0x0e,
			self::ARG_TYPE_STRING => 0x1b,
			self::ARG_TYPE_POSITION => 0x1d,
			self::ARG_TYPE_MESSAGE => 0x20,
			self::ARG_TYPE_RAWTEXT => 0x22,
			self::ARG_TYPE_JSON => 0x25,
			self::ARG_TYPE_COMMAND => 0x2c,
			default => $type,
		};
	}

	protected function decodePayload(PacketSerializer $in) : void{
		/** @var string[] $enumValues */
		$enumValues = [];
		for($i = 0, $enumValuesCount = $in->getUnsignedVarInt(); $i < $enumValuesCount; ++$i){
			$enumValues[] = $in->getString();
		}

		/** @var string[] $postfixes */
		$postfixes = [];
		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			$postfixes[] = $in->getString();
		}

		/** @var CommandEnum[] $enums */
		$enums = [];
		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			$enums[] = $enum = $this->getEnum($enumValues, $in);
			if(isset(self::HARDCODED_ENUM_NAMES[$enum->getName()])){
				$this->hardcodedEnums[] = $enum;
			}
		}

		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			$this->commandData[] = $this->getCommandData($enums, $postfixes, $in);
		}

		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			$this->softEnums[] = $this->getSoftEnum($in);
		}

		if($in->getProtocolId() >= ProtocolInfo::PROTOCOL_1_13_0){
			for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
				$this->enumConstraints[] = $this->getEnumConstraint($enums, $enumValues, $in);
			}
		}
	}

	/**
	 * @param string[] $enumValueList
	 *
	 * @throws PacketDecodeException
	 * @throws BinaryDataException
	 */
	protected function getEnum(array $enumValueList, PacketSerializer $in) : CommandEnum{
		$enumName = $in->getString();
		$enumValues = [];

		$listSize = count($enumValueList);

		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			$index = $this->getEnumValueIndex($listSize, $in);
			if(!isset($enumValueList[$index])){
				throw new PacketDecodeException("Invalid enum value index $index");
			}
			//Get the enum value from the initial pile of mess
			$enumValues[] = $enumValueList[$index];
		}

		return new CommandEnum($enumName, $enumValues);
	}

	/**
	 * @throws BinaryDataException
	 */
	protected function getSoftEnum(PacketSerializer $in) : CommandEnum{
		$enumName = $in->getString();
		$enumValues = [];

		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			//Get the enum value from the initial pile of mess
			$enumValues[] = $in->getString();
		}

		return new CommandEnum($enumName, $enumValues);
	}

	/**
	 * @param int[]       $enumValueMap
	 */
	protected function putEnum(CommandEnum $enum, array $enumValueMap, PacketSerializer $out) : void{
		$out->putString($enum->getName());

		$values = $enum->getValues();
		$out->putUnsignedVarInt(count($values));
		$listSize = count($enumValueMap);
		foreach($values as $value){
			if(!isset($enumValueMap[$value])){
				throw new \LogicException("Enum value '$value' doesn't have a value index");
			}
			$this->putEnumValueIndex($enumValueMap[$value], $listSize, $out);
		}
	}

	protected function putSoftEnum(CommandEnum $enum, PacketSerializer $out) : void{
		$out->putString($enum->getName());

		$values = $enum->getValues();
		$out->putUnsignedVarInt(count($values));
		foreach($values as $value){
			$out->putString($value);
		}
	}

	/**
	 * @throws BinaryDataException
	 */
	protected function getEnumValueIndex(int $valueCount, PacketSerializer $in) : int{
		if($valueCount < 256){
			return $in->getByte();
		}elseif($valueCount < 65536){
			return $in->getLShort();
		}else{
			return $in->getLInt();
		}
	}

	protected function putEnumValueIndex(int $index, int $valueCount, PacketSerializer $out) : void{
		if($valueCount < 256){
			$out->putByte($index);
		}elseif($valueCount < 65536){
			$out->putLShort($index);
		}else{
			$out->putLInt($index);
		}
	}

	/**
	 * @param CommandEnum[] $enums
	 * @param string[]      $enumValues
	 *
	 * @throws PacketDecodeException
	 * @throws BinaryDataException
	 */
	protected function getEnumConstraint(array $enums, array $enumValues, PacketSerializer $in) : CommandEnumConstraint{
		//wtf, what was wrong with an offset inside the enum? :(
		$valueIndex = $in->getLInt();
		if(!isset($enumValues[$valueIndex])){
			throw new PacketDecodeException("Enum constraint refers to unknown enum value index $valueIndex");
		}
		$enumIndex = $in->getLInt();
		if(!isset($enums[$enumIndex])){
			throw new PacketDecodeException("Enum constraint refers to unknown enum index $enumIndex");
		}
		$enum = $enums[$enumIndex];
		$valueOffset = array_search($enumValues[$valueIndex], $enum->getValues(), true);
		if($valueOffset === false){
			throw new PacketDecodeException("Value \"" . $enumValues[$valueIndex] . "\" does not belong to enum \"" . $enum->getName() . "\"");
		}

		$constraintIds = [];
		for($i = 0, $count = $in->getUnsignedVarInt(); $i < $count; ++$i){
			$constraintIds[] = $in->getByte();
		}

		return new CommandEnumConstraint($enum, $valueOffset, $constraintIds);
	}

	/**
	 * @param int[]                 $enumIndexes string enum name -> int index
	 * @param int[]                 $enumValueIndexes string value -> int index
	 */
	protected function putEnumConstraint(CommandEnumConstraint $constraint, array $enumIndexes, array $enumValueIndexes, PacketSerializer $out) : void{
		$out->putLInt($enumValueIndexes[$constraint->getAffectedValue()]);
		$out->putLInt($enumIndexes[$constraint->getEnum()->getName()]);
		$out->putUnsignedVarInt(count($constraint->getConstraints()));
		foreach($constraint->getConstraints() as $v){
			$out->putByte($v);
		}
	}

	/**
	 * @param CommandEnum[] $enums
	 * @param string[]      $postfixes
	 *
	 * @throws PacketDecodeException
	 * @throws BinaryDataException
	 */
	protected function getCommandData(array $enums, array $postfixes, PacketSerializer $in) : CommandData{
		$name = $in->getString();
		$description = $in->getString();
		if($in->getProtocolId() >= ProtocolInfo::PROTOCOL_1_17_10){
			$flags = $in->getLShort();
		}else{
			$flags = $in->getByte();
		}
		$permission = $in->getByte();
		$aliases = $enums[$in->getLInt()] ?? null;
		$overloads = [];

		for($overloadIndex = 0, $overloadCount = $in->getUnsignedVarInt(); $overloadIndex < $overloadCount; ++$overloadIndex){
			$overloads[$overloadIndex] = [];
			for($paramIndex = 0, $paramCount = $in->getUnsignedVarInt(); $paramIndex < $paramCount; ++$paramIndex){
				$parameter = new CommandParameter();
				$parameter->paramName = $in->getString();
				$parameter->paramType = $in->getLInt();
				$parameter->isOptional = $in->getBool();
				$parameter->flags = $in->getByte();

				if(($parameter->paramType & self::ARG_FLAG_ENUM) !== 0){
					$index = ($parameter->paramType & 0xffff);
					$parameter->enum = $enums[$index] ?? null;
					if($parameter->enum === null){
						throw new PacketDecodeException("deserializing $name parameter $parameter->paramName: expected enum at $index, but got none");
					}
				}elseif(($parameter->paramType & self::ARG_FLAG_POSTFIX) !== 0){
					$index = ($parameter->paramType & 0xffff);
					$parameter->postfix = $postfixes[$index] ?? null;
					if($parameter->postfix === null){
						throw new PacketDecodeException("deserializing $name parameter $parameter->paramName: expected postfix at $index, but got none");
					}
				}elseif(($parameter->paramType & self::ARG_FLAG_VALID) === 0){
					throw new PacketDecodeException("deserializing $name parameter $parameter->paramName: Invalid parameter type 0x" . dechex($parameter->paramType));
				}

				$overloads[$overloadIndex][$paramIndex] = $parameter;
			}
		}

		return new CommandData($name, $description, $flags, $permission, $aliases, $overloads);
	}

	/**
	 * @param int[]       $enumIndexes string enum name -> int index
	 * @param int[]       $postfixIndexes
	 */
	protected function putCommandData(CommandData $data, array $enumIndexes, array $postfixIndexes, PacketSerializer $out) : void{
		$out->putString($data->name);
		$out->putString($data->description);
		if($out->getProtocolId() >= ProtocolInfo::PROTOCOL_1_17_10){
			$out->putLShort($data->flags);
		}else{
			$out->putByte($data->flags);
		}
		$out->putByte($data->permission);

		if($data->aliases !== null){
			$out->putLInt($enumIndexes[$data->aliases->getName()] ?? -1);
		}else{
			$out->putLInt(-1);
		}

		$out->putUnsignedVarInt(count($data->overloads));
		foreach($data->overloads as $overload){
			/** @var CommandParameter[] $overload */
			$out->putUnsignedVarInt(count($overload));
			foreach($overload as $parameter){
				$out->putString($parameter->paramName);

				if($parameter->enum !== null){
					$type = self::ARG_FLAG_ENUM | self::ARG_FLAG_VALID | ($enumIndexes[$parameter->enum->getName()] ?? -1);
				}elseif($parameter->postfix !== null){
					if(!isset($postfixIndexes[$parameter->postfix])){
						throw new \LogicException("Postfix '$parameter->postfix' not in postfixes array");
					}
					$type = self::ARG_FLAG_POSTFIX | $postfixIndexes[$parameter->postfix];
				}else{
					$type = $parameter->paramType;
				}

				$out->putLInt($type);
				$out->putBool($parameter->isOptional);
				$out->putByte($parameter->flags);
			}
		}
	}

	protected function encodePayload(PacketSerializer $out) : void{
		/** @var int[] $enumValueIndexes */
		$enumValueIndexes = [];
		/** @var int[] $postfixIndexes */
		$postfixIndexes = [];
		/** @var int[] $enumIndexes */
		$enumIndexes = [];
		/** @var CommandEnum[] $enums */
		$enums = [];

		$addEnumFn = static function(CommandEnum $enum) use (&$enums, &$enumIndexes, &$enumValueIndexes) : void{
			if(!isset($enumIndexes[$enum->getName()])){
				$enums[$enumIndexes[$enum->getName()] = count($enumIndexes)] = $enum;
			}
			foreach($enum->getValues() as $str){
				$enumValueIndexes[$str] = $enumValueIndexes[$str] ?? count($enumValueIndexes); //latest index
			}
		};
		foreach($this->hardcodedEnums as $enum){
			$addEnumFn($enum);
		}
		foreach($this->commandData as $commandData){
			if($commandData->aliases !== null){
				$addEnumFn($commandData->aliases);
			}
			/** @var CommandParameter[] $overload */
			foreach($commandData->overloads as $overload){
				/** @var CommandParameter $parameter */
				foreach($overload as $parameter){
					if($parameter->enum !== null){
						$addEnumFn($parameter->enum);
					}

					if($parameter->postfix !== null){
						$postfixIndexes[$parameter->postfix] = $postfixIndexes[$parameter->postfix] ?? count($postfixIndexes);
					}
				}
			}
		}

		$out->putUnsignedVarInt(count($enumValueIndexes));
		foreach($enumValueIndexes as $enumValue => $index){
			$out->putString((string) $enumValue); //stupid PHP key casting D:
		}

		$out->putUnsignedVarInt(count($postfixIndexes));
		foreach($postfixIndexes as $postfix => $index){
			$out->putString((string) $postfix); //stupid PHP key casting D:
		}

		$out->putUnsignedVarInt(count($enums));
		foreach($enums as $enum){
			$this->putEnum($enum, $enumValueIndexes, $out);
		}

		$out->putUnsignedVarInt(count($this->commandData));
		foreach($this->commandData as $data){
			$this->putCommandData($data, $enumIndexes, $postfixIndexes, $out);
		}

		$out->putUnsignedVarInt(count($this->softEnums));
		foreach($this->softEnums as $enum){
			$this->putSoftEnum($enum, $out);
		}

		if($out->getProtocolId() >= ProtocolInfo::PROTOCOL_1_13_0){
			$out->putUnsignedVarInt(count($this->enumConstraints));
			foreach($this->enumConstraints as $constraint){
				$this->putEnumConstraint($constraint, $enumIndexes, $enumValueIndexes, $out);
			}
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handleAvailableCommands($this);
	}
}
