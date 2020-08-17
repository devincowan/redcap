alter table redcap_messages_recipients
	drop index `thread_id_users`,
	add key `thread_id_users` (`thread_id`,`all_users`);
alter table redcap_messages_threads
	drop index `type_channel`,
	add key `type_channel` (`type`,`channel_name`);