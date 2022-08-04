<?php
/**
 * Copyright (c) 2015 - 2019 Molkobain.
 *
 * This file is part of licensed extension.
 *
 * Use of this extension is bound by the license you purchased. A license grants you a non-exclusive and non-transferable right to use and incorporate the item in your personal or commercial projects. There are several licenses available (see https://www.molkobain.com/usage-licenses/ for more informations)
 */

Dict::Add('EN US', 'English', 'English', array(
	
	'Class:ThirdPartyNewsRoomMessage' => 'Third party Newsroom Message',
	'Class:ThirdPartyNewsRoomMessage/Attribute:thirdparty_name' => 'Third party name',
	'Class:ThirdPartyNewsRoomMessage/Attribute:thirdparty_name+' => 'Name of third party that published this message.',
	'Class:ThirdPartyNewsRoomMessage/Attribute:thirdparty_message_id' => 'Third party message ID',
	'Class:ThirdPartyNewsRoomMessage/Attribute:thirdparty_message_id+' => 'Unique message identifier of third party.',
	'Class:ThirdPartyNewsRoomMessage/Attribute:title' => 'Title',
	'Class:ThirdPartyNewsRoomMessage/Attribute:title+' => 'Message title',
	'Class:ThirdPartyNewsRoomMessage/Attribute:start_date' => 'Start date',
	'Class:ThirdPartyNewsRoomMessage/Attribute:start_date+' => 'Date on which message will become visible to user.',
	'Class:ThirdPartyNewsRoomMessage/Attribute:end_date' => 'End date',
	'Class:ThirdPartyNewsRoomMessage/Attribute:end_date+' => 'Date on which message will no longer be visible to user.',
	'Class:ThirdPartyNewsRoomMessage/Attribute:priority' => 'Priority',
	'Class:ThirdPartyNewsRoomMessage/Attribute:priority+' => 'Priority. 1 = highest, 4 = lowest.',
	'Class:ThirdPartyNewsRoomMessage/Attribute:icon' => 'Icon',
	'Class:ThirdPartyNewsRoomMessage/Attribute:icon+' => 'Icon to appear next to message in the newsroom',
	'Class:ThirdPartyNewsRoomMessage/Attribute:translations_list' => 'Translations',
	'Class:ThirdPartyNewsRoomMessage/Attribute:translations_list+' => 'Localized versions of the news message',
	'Class:ThirdPartyNewsRoomMessage/Attribute:target_profiles' => 'Target profiles',
	'Class:ThirdPartyNewsRoomMessage/Attribute:target_profiles+' => 'Profiles which will see this message. If empty, every user profile will see this message.',
	'Class:ThirdPartyNewsRoomMessage/Attribute:oql' => 'OQL',
	'Class:ThirdPartyNewsRoomMessage/Attribute:oql+' => 'The OQL which specifies which users should see this message.',
	'Class:ThirdPartyNewsRoomMessage/Check:OQLMustReturnUser' => 'The OQL must return user objects, currently it returns %1$s objects.',
	
	'Class:ThirdPartyNewsRoomMessageTranslation' => 'Third party Newsroom Message Translation',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:message_id' => 'Message ID',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:message_id+' => 'Message that will be translated',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:title' => 'Title',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:title+' => 'Message title',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:text' => 'Text',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:text+' => 'Text',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:url' => 'URL',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:url+' => 'Users will visit this web page upon clicking the message.',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:language' => 'Language',
	'Class:ThirdPartyNewsRoomMessageTranslation/Attribute:language+' => 'Targeted user language',
	'Class:ThirdPartyNewsRoomMessageTranslation/UniquenessRule:unique_language' => 'Language for this translation must be unique for each message.',
	
	'Class:ThirdPartyMessageToUser' => 'Third party Unread Newsroom Message to User',
	'Class:ThirdPartyMessageToUser/Attribute:user_id' => 'User ID',
	'Class:ThirdPartyMessageToUser/Attribute:message_id' => 'Message ID',
	'Class:ThirdPartyMessageToUser/Attribute:read_date' => 'Date',
	
	'UI:News:AllMessages' => 'All messages',
	'UI:News:MoreInfo' => 'More info',
	
	'ThirdPartyNewsRoomMessage:info' => 'Info',
	'ThirdPartyNewsRoomMessage:publication' => 'Publication',
	
));


