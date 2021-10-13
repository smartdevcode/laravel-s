<?php

namespace Hhxsv5\LaravelS\Illuminate;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class LaravelSCommand extends Command
{
    protected $signature = 'laravels';

    protected $description = 'LaravelS console tool';

    protected $actions;

    protected $isLumen = false;

    public function __construct()
    {
        $this->actions = ['start', 'stop', 'restart', 'reload', 'publish'];
        $actions = implode('|', $this->actions);
        $this->signature .= sprintf(' {action : %s} {--d|daemonize : Whether run as a daemon for start & restart}', $actions);
        $this->description .= ': ' . $actions;

        parent::__construct();
    }

    public function fire()
    {
        $this->handle();
    }

    public function handle()
    {
        $action = (string)$this->argument('action');
        if (!in_array($action, $this->actions, true)) {
            $this->warn(sprintf('LaravelS: action %s is not available, only support %s', $action, implode('|', $this->actions)));
            return 127;
        }

        $this->isLumen = stripos($this->getApplication()->getVersion(), 'Lumen') !== false;
        $this->loadConfigManually();
        return $this->{$action}();
    }

    protected function loadConfigManually()
    {
        // Load configuration laravel.php manually for Lumen
        $basePath = config('laravels.laravel_base_path') ?: base_path();
        if ($this->isLumen && file_exists($basePath . '/config/laravels.php')) {
            $this->getLaravel()->configure('laravels');
        }
    }

    protected function outputLogo()
    {
        static $logo = <<<EOS
 _                               _  _____ 
| |                             | |/ ____|
| |     __ _ _ __ __ ___   _____| | (___  
| |    / _` | '__/ _` \ \ / / _ \ |\___ \ 
| |___| (_| | | | (_| |\ V /  __/ |____) |
|______\__,_|_|  \__,_| \_/ \___|_|_____/ 
                                           
EOS;
        $this->info($logo);
        $this->info('Speed up your Laravel/Lumen');
        $this->table(['Component', 'Version'], [
            ['Component' => 'PHP', 'Version' => phpversion()],
            ['Component' => 'Swoole', 'Version' => \swoole_version()],
            ['Component' => $this->getApplication()->getName(), 'Version' => $this->getApplication()->getVersion()],
        ]);
    }

    protected function start()
    {
        $this->outputLogo();

        $svrConf = config('laravels');
        $basePath = array_get($svrConf, 'laravel_base_path', base_path());

        if (empty($svrConf['swoole']['document_root'])) {
            $svrConf['swoole']['document_root'] = $basePath . '/public';
        }
        if (empty($svrConf['process_prefix'])) {
            $svrConf['process_prefix'] = $basePath;
        }
        if (!empty($svrConf['events'])) {
            if (empty($svrConf['swoole']['task_worker_num']) || $svrConf['swoole']['task_worker_num'] <= 0) {
                $this->error('LaravelS: Asynchronous event listening needs to set task_worker_num > 0');
                return 1;
            }
        }
        if ($this->option('daemonize')) {
            $svrConf['swoole']['daemonize'] = true;
        }

        $laravelConf = [
            'root_path'          => $basePath,
            'static_path'        => $svrConf['swoole']['document_root'],
            'register_providers' => array_unique((array)array_get($svrConf, 'register_providers', [])),
            'is_lumen'           => $this->isLumen,
            '_SERVER'            => $_SERVER,
            '_ENV'               => $_ENV,
        ];

        if (file_exists($svrConf['swoole']['pid_file'])) {
            $pid = (int) file_get_contents($svrConf['swoole']['pid_file']);
            if ($pid > 0 && $this->killProcess($pid, 0)) {
                $this->warn(sprintf('LaravelS: PID[%s] is already running at %s:%s.', $pid, $svrConf['listen_ip'], $svrConf['listen_port']));
                return 1;
            }
        }

        if (!$svrConf['swoole']['daemonize']) {
            $this->info(sprintf('LaravelS: Swoole is listening at %s:%s.', $svrConf['listen_ip'], $svrConf['listen_port']));
        }

        // Implements gracefully reload, avoid including laravel's files before worker start
        $cmd = sprintf('%s %s/../GoLaravelS.php', PHP_BINARY, __DIR__);
        $ret = $this->popen($cmd, json_encode(compact('svrConf', 'laravelConf')));
        if ($ret === false) {
            $this->error('LaravelS: popen ' . $cmd . ' failed');
            return 1;
        }

        $pidFile = empty($svrConf['swoole']['pid_file']) ? storage_path('laravels.pid') : $svrConf['swoole']['pid_file'];

        // Make sure that master process started
        $time = 0;
        while (!file_exists($pidFile) && $time <= 20) {
            usleep(100000);
            $time++;
        }

        if (file_exists($pidFile)) {
            $this->info(sprintf('LaravelS: PID[%s] is listening at %s:%s.', file_get_contents($pidFile), $svrConf['listen_ip'], $svrConf['listen_port']));
            return 0;
        } else {
            $this->error(sprintf('LaravelS: PID file[%s] does not exist.', $pidFile));
            return 1;
        }
    }

    protected function popen($cmd, $input = null)
    {
        $fp = popen($cmd, 'w');
        if ($fp === false) {
            return false;
        }
        if ($input !== null) {
            fwrite($fp, $input);
        }
        pclose($fp);
        return true;
    }

    protected function stop()
    {
        $pidFile = config('laravels.swoole.pid_file') ?: storage_path('laravels.pid');
        if (!file_exists($pidFile)) {
            $this->info('LaravelS: already stopped.');
            return 0;
        }

        $pid = (int)file_get_contents($pidFile);
        if ($this->killProcess($pid, 0)) {
            if ($this->killProcess($pid, SIGTERM)) {
                // Make sure that master process quit
                $time = 1;
                $waitTime = config('laravels.swoole.max_wait_time', 60);
                while ($this->killProcess($pid, 0)) {
                    $this->warn("LaravelS: Waiting PID[{$pid}] to stop. [{$time}]");
                    if ($time > $waitTime) {
                        $this->error("LaravelS: PID[{$pid}] cannot be stopped gracefully in {$waitTime}s, will be stopped forced right now.");
                        return 1;
                    }
                    sleep(1);
                    $time++;
                }
                if (file_exists($pidFile)) {
                    unlink($pidFile);
                }
                $this->info("LaravelS: PID[{$pid}] is stopped.");
                return 0;
            } else {
                $this->error("LaravelS: PID[{$pid}] is stopped failed.");
                return 1;
            }
        } else {
            $this->warn("LaravelS: PID[{$pid}] does not exist, or permission denied.");
            if (file_exists($pidFile)) {
                unlink($pidFile);
            }
            return 1;
        }
    }

    protected function restart()
    {
        if (($exitCode = $this->stop()) !== 0) {
            return $exitCode;
        }
        $exitCode = $this->start();
        return $exitCode;
    }

    protected function reload()
    {
        $pidFile = config('laravels.swoole.pid_file') ?: storage_path('laravels.pid');
        if (!file_exists($pidFile)) {
            $this->error('LaravelS: it seems that LaravelS is not running.');
            return 1;
        }

        $pid = (int) file_get_contents($pidFile);
        if (!$this->killProcess($pid, 0)) {
            $this->error("LaravelS: PID[{$pid}] does not exist, or permission denied.");
            return 1;
        }

        if ($this->killProcess($pid, SIGUSR1)) {
            $this->info("LaravelS: PID[{$pid}] is reloaded.");
            return 0;
        } else {
            $this->error("LaravelS: PID[{$pid}] is reloaded failed.");
            return 1;
        }
    }

    protected function publish()
    {
        $basePath = config('laravels.laravel_base_path') ?: base_path();
        $to = $basePath . '/config/laravels.php';
        if (file_exists($to)) {
            $choice = $this->anticipate($to . ' already exists, do you want to override it ? Y/N', ['Y', 'N'], 'N');
            if (!$choice || strtoupper($choice) !== 'Y') {
                $this->info('Publishing skipped.');
                return 0;
            }
        }

        try {
            return $this->call('vendor:publish', ['--provider' => LaravelSServiceProvider::class, '--force' => true]);
        } catch (\InvalidArgumentException $e) {
            // do nothing.
        } catch (\Exception $e) {
            throw $e;
        }

        $from = __DIR__ . '/../../config/laravels.php';
        $toDir = dirname($to);

        /**
         * @var Filesystem $files
         */
        $files = app(Filesystem::class);

        if (!$files->isDirectory($toDir)) {
            $files->makeDirectory($toDir, 0755, true);
        }

        $files->copy($from, $to);

        $from = str_replace($basePath, '', realpath($from));

        $to = str_replace($basePath, '', realpath($to));

        $this->line('<info>Copied File</info> <comment>[' . $from . ']</comment> <info>To</info> <comment>[' . $to . ']</comment>');
        return 0;
    }

    protected function killProcess($pid, $sig)
    {
        try {
            return \swoole_process::kill($pid, $sig);
        } catch (\Exception $e) {
            return false;
        }
    }
}
