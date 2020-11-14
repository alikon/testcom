-- #__tasks definition

-- Drop table

-- DROP TABLE #__tasks;

CREATE TABLE #__tasks (
	id serial NOT NULL,
	taskname varchar(100) NOT NULL,
	duration numeric(8,2) NOT NULL DEFAULT 0,
	jobid int8 NOT NULL,
	taskid int8 NOT NULL,
	exitcode int2 NOT NULL,
	lastdate timestamp NOT NULL,
	nextdate timestamp NOT NULL,
	CONSTRAINT #__tasks_pkey PRIMARY KEY (id)
);