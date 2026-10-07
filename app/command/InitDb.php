<?php

namespace app\command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use app\services\DbInit;

#[AsCommand('db:init', '初始化 sql.db：建表并补种商店/成就数据')]
class InitDb extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DbInit::run();
        $output->writeln('<info>sql.db 初始化完成：表结构 + 商店/成就种子数据已就绪</info>');
        return self::SUCCESS;
    }
}
