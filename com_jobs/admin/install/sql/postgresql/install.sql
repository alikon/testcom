-- #__tasks definition

-- Drop table

-- DROP TABLE #__tasks;

CREATE TABLE IF NOT EXISTS "#__tasks" (
	"id" serial NOT NULL,
	"taskname" varchar(100) NOT NULL,
	"duration" numeric(8,2) NOT NULL DEFAULT 0,
	"jobid" int8 NOT NULL,
	"taskid" int8 NOT NULL,
	"exitcode" int2 NOT NULL,
	"lastdate" timestamp NOT NULL,
	"nextdate" timestamp NOT NULL,
	"frequency" int8 NOT NULL,
	"unit" varchar(100) NOT NULL,
	CONSTRAINT #__tasks_pkey PRIMARY KEY (id)
);

CREATE TABLE IF NOT EXISTS "#__jobs" (
  "element" varchar(100) NOT NULL DEFAULT ''::character varying,
  "folder" varchar(100) NOT NULL DEFAULT ''::character varying,
  CONSTRAINT "#__jobs_pkey" PRIMARY KEY ("element", "folder")
);
