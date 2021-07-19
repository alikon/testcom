REM This will generate the zipfiles for Com_jobs in /build/packages

rmdir /q /s packages
mkdir packages
echo d | xcopy pkg_jobs.xml packages/

REM Component com_jobs
cd ../com_jobs/
zip -r ../build/packages/com_jobs.zip *

REM Plugins

REM Plugin console Job
cd ../plugins/console/job/
zip -r ../../../build/packages/plg_console_job.zip *

REM Plugin webservices jobs
cd ../../webservices/jobs/
zip -r ../../../build/packages/plg_webservices_jobs.zip *

REM Plugins
cd ../../job/cleancache/
zip -r ../../../build/packages/plg_job_cleancache.zip *

REM Plugins
cd ../exportdb/
zip -r ../../../build/packages/plg_job_exportdb.zip *

REM Plugins
cd ../expiredconsent/
zip -r ../../../build/packages/plg_job_expiredconsent.zip *

REM Plugins
cd ../logrotation/
zip -r ../../../build/packages/plg_job_logrotation.zip *

REM Plugins
cd ../startafriend/
zip -r ../../../build/packages/plg_job_startafriend.zip *

REM Plugins
cd ../../system/scheduler/
zip -r ../../../build/packages/plg_system_scheduler.zip *

REM Package
cd ../../../build/packages/
zip -r pkg_jobs.zip *
