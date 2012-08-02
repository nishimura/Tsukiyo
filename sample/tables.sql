DROP TABLE IF EXISTS sub_sub_item;
DROP TABLE IF EXISTS sub_item;
DROP TABLE IF EXISTS item;

CREATE TABLE item(
  item_id    serial primary key,
  name       text not null,
  created_at timestamp not null
);

CREATE TABLE sub_item(
  sub_item_id serial primary key,
  item_id     int not null references item(item_id) on delete cascade,
  name        text not null,
  opt         text
);

CREATE TABLE sub_sub_item(
  sub_sub_item_id serial primary key,
  sub_item_id     int not null references sub_item(sub_item_id) on delete cascade,
  val             int not null
);

