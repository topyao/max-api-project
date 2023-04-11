<?php

namespace App\Console\Command\Server;

use App\Http\Kernel;
use App\Http\ServerRequest;
use Exception;
use Max\Di\Context;
use Max\Http\Server\Event\OnRequest;
use Max\Http\Server\ResponseEmitter\WorkerManResponseEmitter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http\Request;
use Workerman\Worker;

class WorkermanServerCommand extends BaseServerCommand
{
    protected string $container = 'workerman';

    protected function configure()
    {
        $this->setName('serve:workerman')
             ->setDescription('Manage workerman server')
             ->addArgument('action')
             ->addOption('d');
    }

    /**
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!class_exists('Workerman\Worker')) {
            throw new Exception('You should install the workerman via `composer require workerman/workerman` command before starting.');
        }
        global $argv;
        $action  = $input->getArgument('action');
        $argv[0] = 'serve:workerman';
        $argv[1] = $action;
        $argv[2] = $input->getOption('d') ? '-d' : '';
        (function () {
            $worker            = new Worker(sprintf('http://%s:%d', $this->host, $this->port));
            $container         = Context::getContainer();
            $kernel            = $container->make(Kernel::class);
            $eventDispatcher   = $container->make(\Max\Event\EventDispatcher::class);
            $worker->onMessage = function (TcpConnection $connection, Request $request) use ($kernel, $eventDispatcher) {
                $psrRequest  = ServerRequest::createFromWorkerManRequest($request, ['TcpConnection' => $connection, 'request' => $request]);
                $psrResponse = $kernel->handle($psrRequest);
                (new WorkerManResponseEmitter())->emit($psrResponse, $connection);
                $eventDispatcher->dispatch(new OnRequest($psrRequest, $psrResponse));
            };
            $worker->count     = 4;
            $this->showInfo();
            Worker::runAll();
        })();

        return 0;
    }
}
