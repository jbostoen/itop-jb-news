<?php
/**
 * Copyright (c) 2015 - 2019 Molkobain.
 *
 * This file is part of licensed extension.
 *
 * Use of this extension is bound by the license you purchased. A license grants you a non-exclusive and non-transferable right to use and incorporate the item in your personal or commercial projects. There are several licenses available (see https://www.molkobain.com/usage-licenses/ for more informations)
 */

Dict::Add('EN US', 'English', 'English', array(
	
	'Class:ThirdPartyNewsMessage' => 'Third party Newsroom Message',
	'Class:ThirdPartyNewsMessage/Attribute:thirdparty_name' => 'Third party name',
	'Class:ThirdPartyNewsMessage/Attribute:thirdparty_name+' => 'Name of third party that published this message.',
	'Class:ThirdPartyNewsMessage/Attribute:thirdparty_message_id' => 'Third party message ID',
	'Class:ThirdPartyNewsMessage/Attribute:thirdparty_message_id+' => 'Unique message identifier of third party.',
	'Class:ThirdPartyNewsMessage/Attribute:title' => 'Title',
	'Class:ThirdPartyNewsMessage/Attribute:title+' => 'Message title',
	'Class:ThirdPartyNewsMessage/Attribute:start_date' => 'Start date',
	'Class:ThirdPartyNewsMessage/Attribute:start_date+' => 'Date on which message will become visible to user.',
	'Class:ThirdPartyNewsMessage/Attribute:end_date' => 'End date',
	'Class:ThirdPartyNewsMessage/Attribute:end_date+' => 'Date on which message will no longer be visible to user.',
	'Class:ThirdPartyNewsMessage/Attribute:priority' => 'Priority',
	'Class:ThirdPartyNewsMessage/Attribute:priority+' => 'Priority.',
	'Class:ThirdPartyNewsMessage/Attribute:priority/Value:1' => 'Critical',
	'Class:ThirdPartyNewsMessage/Attribute:priority/Value:2' => 'Urgent',
	'Class:ThirdPartyNewsMessage/Attribute:priority/Value:3' => 'Important',
	'Class:ThirdPartyNewsMessage/Attribute:priority/Value:4' => 'Standard',
	'Class:ThirdPartyNewsMessage/Attribute:icon' => 'Icon',
	'Class:ThirdPartyNewsMessage/Attribute:icon+' => 'Icon to appear next to message in the newsroom',
	'Class:ThirdPartyNewsMessage/Attribute:translations_list' => 'Translations',
	'Class:ThirdPartyNewsMessage/Attribute:translations_list+' => 'Localized versions of the news message',
	'Class:ThirdPartyNewsMessage/Attribute:oql' => 'OQL',
	'Class:ThirdPartyNewsMessage/Attribute:oql+' => 'The OQL that specifies which users should see this message.',
	'Class:ThirdPartyNewsMessage/Attribute:manually_created' => 'Is source',
	'Class:ThirdPartyNewsMessage/Attribute:manually_created+' => 'Whether or not the message is the original source message (and should not be deleted by the background task).',
	'Class:ThirdPartyNewsMessage/Attribute:manually_created/Value:yes' => 'Yes',
	'Class:ThirdPartyNewsMessage/Attribute:manually_created/Value:no' => 'No',
	'Class:ThirdPartyNewsMessage/Check:OQLMustReturnUser' => 'The OQL must return User objects, currently it returns %1$s objects.',
	'Class:ThirdPartyNewsMessage/UniquenessRule:thirdparty_name_message_id' => 'The combination of the third-party name and third-party message ID must be unique.',
	
	'Class:ThirdPartyNewsMessageTranslation' => 'Third party Newsroom Message Translation',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:message_id' => 'Message ID',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:message_id+' => 'Message that will be translated',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:title' => 'Title',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:title+' => 'Message title',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:text' => 'Text',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:text+' => 'Text',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:url' => 'URL',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:url+' => 'Users will visit this web page upon clicking the message.',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:language' => 'Language',
	'Class:ThirdPartyNewsMessageTranslation/Attribute:language+' => 'Targeted user language',
	'Class:ThirdPartyNewsMessageTranslation/UniquenessRule:unique_language' => 'Language for this translation must be unique for each message.',
	
	'Class:ThirdPartyMessageUserStatus' => 'Link Third party Newsroom Message to User',
	'Class:ThirdPartyMessageUserStatus/Attribute:user_id' => 'User ID',
	'Class:ThirdPartyMessageUserStatus/Attribute:message_id' => 'Message ID',
	'Class:ThirdPartyMessageUserStatus/Attribute:read_date' => 'Marked as read',
	'Class:ThirdPartyMessageUserStatus/Attribute:first_shown_date' => 'First shown',
	'Class:ThirdPartyMessageUserStatus/Attribute:last_shown_date' => 'Last shown',
	
	'UI:News:AllMessages' => 'All messages',
	'UI:News:MoreInfo' => 'More info',
	
	'ThirdPartyNewsMessage:Info' => 'Info',
	'ThirdPartyNewsMessage:Publication' => 'Publication',
	
));


