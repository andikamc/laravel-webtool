<?php
namespace Sadatech\Webtool\Console\Traits;

use App\JobTrace;
use Carbon\Carbon;
use Sadatech\Webtool\Helpers\Common;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage as FileStorage;

trait WorkerGenerator
{
    /**
     * Generate Export Files
     * 
     * @return void
     */
    public function WebtoolDoWorker()
    {
        $_[] = $this->call("queue:work", ["--once" => null, "--tries" => Common::GetEnv('WORKER_TRIES', 3), "--timeout" => Common::GetEnv('WORKER_TIMEOUT', 1200), "--memory" => Common::GetEnv('WORKER_MEMORY', 4096), "--delay" => Common::GetEnv('WORKER_DELAY', 3), "--sleep" => Common::GetEnv('WORKER_SLEEP', 3), "--no-ansi" => null, "--no-interaction" => null, "-vvv" => null]);
        $_[] = $this->WebtoolValidateSyncFiles();
        $_[] = $this->WebtoolDoExportSyncFiles();
        $_[] = shell_exec('$(which sleep) 15');
    }

    /**
     * Generate Validate Sync Files
     * 
     * @return void
     */
    private function WebtoolValidateSyncFiles()
    {
        /**
         * Validate sync files
         */
        $jobtraces = JobTrace::whereIn('status', ['FAILED'])->where('explanation', 'LIKE', '%Permission denied%')->orderByDesc('created_at')->get();

        foreach ($jobtraces as $tracejob)
        {
            $ndate = Carbon::now()->timestamp;
            $mdate = Carbon::parse($tracejob->created_at)->addDays(Common::GetEnv('EXPORT_EXPIRED_DAYS', 3))->timestamp;
            $localfile = str_replace('https://'.request()->getHost().'/', '/', $tracejob->results);
            $localfile = str_replace('https','---123---', str_replace('http','---123---', $localfile));
            $localfile = str_replace('---123---', 'https', $localfile);
            $localfile = str_replace(public_path(''), null, $localfile);
            $cloudfile = "export-data/".str_replace('//', '/', str_replace('_', '-', Common::GetConfig("database.connections.mysql.database"))."/".$localfile);
            $hashfile  = hash('md5', $tracejob->results);

            if ($mdate < $ndate)
            {
                if (File::exists(public_path($localfile)))
                {
                    File::delete(public_path($localfile));
                }

                if (FileStorage::disk("spaces")->exists($cloudfile))
                {
                    FileStorage::disk("spaces")->delete($cloudfile);
                }

                JobTrace::where('id', $tracejob->id)->first()->update([
                    'status' => 'DELETED',
                    'log' => 'File may no longer be available due to an export error or the file has expired. (WebtoolValidateSyncFiles_MNdate_01)',
                ]);
            }
            else
            {
                if (File::exists(public_path($localfile)))
                {
                    if (!FileStorage::disk("spaces")->exists($cloudfile))
                    {
                        JobTrace::where('id', $tracejob->id)->first()->update([
                            'status' => 'PROCESSING',
                        ]);
                    }
                }
                else
                {
                    if (!$tracejob->url)
                    {
                        JobTrace::where('id', $tracejob->id)->first()->update([
                            'status' => 'DELETED',
                            'log' => 'File may no longer be available due to an export error or the file has expired. (WebtoolValidateSyncFiles_FErr_01)',
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Generate Export Sync Files
     * 
     * @return void
     */
    private function WebtoolDoExportSyncFiles()
    {
        $jobtraces = JobTrace::whereIn('status', ['DONE'])->orderByDesc('created_at')->get();
        $jobfilter = [];

        foreach ($jobtraces as $tracejob)
        {
            $ndate = Carbon::now()->timestamp;
            $mdate = Carbon::parse($tracejob->created_at)->addDays(Common::GetEnv('EXPORT_EXPIRED_DAYS', 3))->timestamp;
            $localfile = str_replace('https://'.request()->getHost().'/', '/', $tracejob->results);
            $localfile = str_replace('https','---123---', str_replace('http','---123---', $localfile));
            $localfile = str_replace('---123---', 'https', $localfile);
            $localfile = str_replace(public_path(''), null, $localfile);
            $cloudfile = "export-data/".str_replace('//', '/', str_replace('_', '-', Common::GetConfig("database.connections.mysql.database"))."/".$localfile);
            $hashfile  = hash('md5', $tracejob->results);

            if ($mdate < $ndate)
            {
                if (File::exists(public_path($localfile)))
                {
                    File::delete(public_path($localfile));
                }

                if (FileStorage::disk("spaces")->exists($cloudfile))
                {
                    FileStorage::disk("spaces")->delete($cloudfile);
                }

                JobTrace::where('id', $tracejob->id)->first()->update([
                    'status' => 'DELETED',
                    'log' => 'File may no longer be available due to an export error or the file has expired. (WebtoolExportSyncFiles_MNdate_01)',
                ]);
            }
            else
            {
                if (File::exists(public_path($localfile)))
                {
                    if (!FileStorage::disk("spaces")->exists($cloudfile))
                    {
                        JobTrace::where('id', $tracejob->id)->first()->update([
                            'explanation' => 'Please wait a moment, file is under sync to CDN servers.',
                            'log' => 'Please wait a moment, file is under sync to CDN servers.',
                            'status' => 'PROCESSING',
                        ]);

                        // handler read file
                        try
                        {
                            $filereader = fopen(public_path($localfile), 'r+');
                            if (FileStorage::disk("spaces")->put($cloudfile, $filereader, "public"))
                            {
                                File::delete(public_path($localfile));
                                $cloudurl = str_replace('https://'.Common::GetConfig('filesystems.disks.spaces.bucket').str_replace('https://', '.', Common::GetConfig('filesystems.disks.spaces.endpoint')), Common::GetConfig('filesystems.disks.spaces.url'), FileStorage::disk("spaces")->url($cloudfile));
                                JobTrace::where('id', $tracejob->id)->first()->update([
                                    'explanation' => 'File archived on CDN servers.',
                                    'log' => 'File archived on CDN servers.',
                                    'url' => $cloudurl,
                                    'status' => 'DONE',
                                ]);
                            }
                            else
                            {
                                JobTrace::where('id', $tracejob->id)->first()->update([
                                    'explanation' => 'Failed sync to CDN servers.',
                                    'log' => 'Failed sync to CDN servers.',
                                    'status' => 'DONE',
                                ]);
                            }
                        }
                        catch (Exception $ex)
                        {
                            JobTrace::where('id', $tracejob->id)->first()->update([
                                'explanation' => $ex->getMessage(),
                                'log' => $ex->getMessage(),
                                'status' => 'FAILED',
                            ]);
                        }
                    }
                    else
                    {
                        File::delete(public_path($localfile));
                        $cloudurl = str_replace('https://'.Common::GetConfig('filesystems.disks.spaces.bucket').str_replace('https://', '.', Common::GetConfig('filesystems.disks.spaces.endpoint')), Common::GetConfig('filesystems.disks.spaces.url'), FileStorage::disk("spaces")->url($cloudfile));
                        JobTrace::where('id', $tracejob->id)->first()->update([
                            'explanation' => 'File archived on CDN servers.',
                            'log' => 'File archived on CDN servers.',
                            'url' => $cloudurl,
                            'status' => 'DONE',
                        ]);
                    }
                }
                else
                {
                    if (!$tracejob->url)
                    {
                        JobTrace::where('id', $tracejob->id)->first()->update([
                            'status' => 'DELETED',
                            'log' => 'File may no longer be available due to an export error or the file has expired. (WebtoolExportSyncFiles_FErr_01)',
                        ]);
                    }
                }
            }
        }
    }

}