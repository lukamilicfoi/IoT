array=("Smith" "Lincoln" "Washington")
echo "INSERT INTO persons(person, time) VALUES('${array[$(($RANDOM % 3))]}', CURRENT_TIMESTAMP);" | psql -U postgres