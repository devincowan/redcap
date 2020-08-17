-- Fix leftover issues from previously fixed bug
update redcap_user_rights u, redcap_user_roles r set u.role_id = null WHERE r.role_id = u.role_id AND u.project_id != r.project_id;