<?php
/**
 * Copyright (c) 2015 - 2019 Molkobain.
 *
 * This file is part of licensed extension.
 *
 * Use of this extension is bound by the license you purchased. A license grants you a non-exclusive and non-transferable right to use and incorporate the item in your personal or commercial projects. There are several licenses available (see https://www.molkobain.com/usage-licenses/ for more informations)
 */

Dict::Add('EN US', 'English', 'English', array(
	
	'Class:ThirdPartyNewsroomMessage' => 'Third party Newsroom Message',
	'Class:ThirdPartyNewsroomMessage/Attribute:thirdparty_name' => 'Third party name',
	'Class:ThirdPartyNewsroomMessage/Attribute:thirdparty_name+' => 'Name of third party that published this message.',
	'Class:ThirdPartyNewsroomMessage/Attribute:thirdparty_message_id' => 'Third party message ID',
	'Class:ThirdPartyNewsroomMessage/Attribute:thirdparty_message_id+' => 'Unique message identifier of third party.',
	'Class:ThirdPartyNewsroomMessage/Attribute:title' => 'Title',
	'Class:ThirdPartyNewsroomMessage/Attribute:title+' => 'Message title',
	'Class:ThirdPartyNewsroomMessage/Attribute:start_date' => 'Start date',
	'Class:ThirdPartyNewsroomMessage/Attribute:start_date+' => 'Date on which message will become visible to user.',
	'Class:ThirdPartyNewsroomMessage/Attribute:end_date' => 'End date',
	'Class:ThirdPartyNewsroomMessage/Attribute:end_date+' => 'Date on which message will no longer be visible to user.',
	'Class:ThirdPartyNewsroomMessage/Attribute:priority' => 'Priority',
	'Class:ThirdPartyNewsroomMessage/Attribute:priority+' => 'Priority',
	'Class:ThirdPartyNewsroomMessage/Attribute:icon' => 'Icon',
	'Class:ThirdPartyNewsroomMessage/Attribute:icon+' => 'Icon to appear next to message in the newsroom',
	'Class:ThirdPartyNewsroomMessage/Attribute:translations_list' => 'Translations',
	'Class:ThirdPartyNewsroomMessage/Attribute:translations_list+' => 'Localized versions of the news message',
	'Class:ThirdPartyNewsroomMessage/Attribute:target_profiles' => 'Target profiles',
	'Class:ThirdPartyNewsroomMessage/Attribute:target_profiles+' => 'Profiles which will see this message. If empty, every user profile will see this message.',
	
	'Class:ThirdPartyNewsroomMessageTranslation' => 'Third party Newsroom Message Translation',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:message_id' => 'Message ID',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:message_id+' => 'Message that will be translated',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:title' => 'Title',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:title+' => 'Message title',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:text' => 'Text',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:text+' => 'Text',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:url' => 'URL',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:url+' => 'Users will visit this web page upon clicking the message.',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:language' => 'Language',
	'Class:ThirdPartyNewsroomMessageTranslation/Attribute:language+' => 'Targeted user language',
	'Class:ThirdPartyNewsroomMessageTranslation/UniquenessRule:unique_language' => 'Language for this translation must be unique for each message.',
	
	'Class:ThirdPartyUnreadMessageToUser' => 'Third party Unread Newsroom Message to User',
	'Class:ThirdPartyUnreadMessageToUser/Attribute:user_id' => 'User ID',
	'Class:ThirdPartyUnreadMessageToUser/Attribute:message_id' => 'Message ID',
	
	'UI:News:AllMessages' => 'All messages',
	
));

