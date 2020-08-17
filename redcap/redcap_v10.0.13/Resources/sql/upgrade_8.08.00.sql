-- Adding new PROMIS Battery functionality
ALTER TABLE `redcap_library_map` ADD `battery` TINYINT(1) NOT NULL DEFAULT '0' AFTER `scoring_type`;
-- Fix record list issue for multi-arm projects
delete r.* from redcap_record_counts r, (select a.project_id from redcap_events_arms a, redcap_record_counts c 
where c.project_id = a.project_id group by a.project_id having count(*) > 1) x where r.project_id = x.project_id;