CREATE TABLE users (
	user_id text not null primary key
);

CREATE TABLE documents (
	document_id int primary key,
	token_xpath text not null,
    token_value_xpath text not null,
    name text not null,
    save_path text not null,
    hash text not null
);
CREATE SEQUENCE document_id_seq;

CREATE TABLE documents_users (
	document_id int not null references documents (document_id) ON UPDATE CASCADE ON DELETE CASCADE,
	user_id text not null references users (user_id) ON UPDATE CASCADE ON DELETE CASCADE,
	primary key (document_id, user_id)
);

CREATE TABLE documents_namespaces (
    document_id int not null references documents (document_id) on delete cascade,
    prefix text not null,
    ns text not null,
    primary key (document_id, prefix)
);

CREATE TABLE tokens (
	document_id int not null references documents (document_id) on delete cascade,
	token_id int not null,
	value text not null,
	primary key (document_id, token_id)
);

CREATE TABLE property_types (
	type_id text primary key
);
INSERT INTO property_types VALUES ('closed list'), ('free text'), ('inflection table'), ('link'), ('boolean');

CREATE TABLE properties (
	document_id int not null references documents (document_id) on delete cascade,
	property_xpath text not null,
	type_id text not null references property_types (type_id),
	name text not null check(name not in ('token_id', 'token', '_offset', '_pagesize')),
    read_only bool not null,
    ord int not null,
	primary key (document_id, property_xpath),
    unique (document_id, ord),
    unique (document_id, name)
);

CREATE TABLE dict_values (
	document_id int not null,
	property_xpath text not null,
	value text not null,
	primary key (document_id, property_xpath, value),
	foreign key (document_id, property_xpath) references properties (document_id, property_xpath) on delete cascade
);

CREATE TABLE orig_values (
	document_id int not null,
	token_id int not null,
	property_xpath text not null,
	value text not null,
	foreign key (document_id, token_id) references tokens (document_id, token_id) on delete cascade,
	foreign key (document_id, property_xpath) references properties (document_id, property_xpath) on delete cascade,
	primary key (document_id, token_id, property_xpath)
);

CREATE TABLE values (
	document_id int not null,
	property_xpath text not null,
	token_id int not null,
	user_id text not null references users (user_id) ON UPDATE CASCADE,
	value text not null,
    date timestamp not null default now(),
	foreign key (document_id, token_id, property_xpath) references orig_values (document_id, token_id, property_xpath) on delete cascade,
	primary key (document_id, token_id, property_xpath, user_id)
);

CREATE TABLE import_tmp (
    id int primary key, 
    xml xml not null
);

CREATE SEQUENCE import_tmp_seq;

CREATE TABLE documents_users_preferences (
	document_id int,
	user_id text,
	key text,
	value text not null,
	primary key (document_id, user_id, key),
	foreign key (document_id, user_id) references documents_users (document_id, user_id)
);
