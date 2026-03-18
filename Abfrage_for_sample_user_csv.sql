 -- User export---
 SELECT u.winname as username, u.email, u.vname as first_name, u.zname as last_name, g.name_of as location_name 
 FROM user u
 INNER JOIN gfgh g ON g.gfghid=u.gfgh_id
 LIMIT 25;