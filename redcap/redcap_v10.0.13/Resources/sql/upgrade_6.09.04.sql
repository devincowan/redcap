-- Fix for survey expiration times
update redcap_surveys set survey_expiration = concat(left(survey_expiration, 10), ' 00:00:00')
	where survey_expiration is not null and length(survey_expiration) = 13;