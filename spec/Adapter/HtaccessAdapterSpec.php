<?php

namespace spec\Firewall\Adapter;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Firewall\Filesystem\Filesystem;
use Firewall\Host\IP;

class HtaccessAdapterSpec extends ObjectBehavior
{
    function let(Filesystem $fileSystem)
    {
        $fileSystem->read('path/to/.htaccess')
            ->willReturn([
                '# BEGIN Firewall',
                'order allow,deny',
                'deny from 123.0.0.1',
                'deny from 123.0.0.2',
                'allow from all',
                '# END Firewall'
            ]);

        $this->beConstructedWith('path/to/.htaccess', $fileSystem);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Firewall\Adapter\HtaccessAdapter');
        $this->shouldImplement('Firewall\Firewall');
    }

    function it_blocks_a_host($fileSystem)
    {
        $fileSystem->write('path/to/.htaccess', [
            '# BEGIN Firewall',
            'order allow,deny',
            'deny from 123.0.0.1',
            'deny from 123.0.0.2',
            'deny from 123.0.0.3',
            'allow from all',
            '# END Firewall'
        ])->shouldBeCalled();

        $this->block(IP::fromString('123.0.0.3'));
    }

    function it_does_not_block_an_already_blocked_host($fileSystem)
    {
        $fileSystem->write('path/to/.htaccess', [
            '# BEGIN Firewall',
            'order allow,deny',
            'deny from 123.0.0.1',
            'deny from 123.0.0.2',
            'allow from all',
            '# END Firewall'
        ])->shouldBeCalled();

        $this->block(IP::fromString('123.0.0.1'));
    }

    function it_blocks_a_host_for_the_first_time($fileSystem)
    {
        $fileSystem->read('path/to/.htaccess')->willReturn([]);

        $fileSystem->write('path/to/.htaccess', [
            '# BEGIN Firewall',
            'order allow,deny',
            'deny from 123.0.0.1',
            'allow from all',
            '# END Firewall'
        ])->shouldBeCalled();

        $this->block(IP::fromString('123.0.0.1'));
    }

    function it_does_not_block_when_end_marker_is_not_found($fileSystem)
    {
        $fileSystem->read('path/to/.htaccess')
            ->willReturn([
                '# BEGIN Firewall',
                'order allow,deny',
                'deny from 123.0.0.1',
                'deny from 123.0.0.2',
                'allow from all',
                //'# END Firewall'
            ]);

        $this->shouldThrow('Firewall\Filesystem\Exception\FileException')
            ->during('block', [IP::fromString('123.0.0.1')]);
    }

    function it_unblocks_a_host($fileSystem)
    {
        $fileSystem->write('path/to/.htaccess', [
            '# BEGIN Firewall',
            'order allow,deny',
            'deny from 123.0.0.2',
            'allow from all',
            '# END Firewall'
        ])->shouldBeCalled();

        $this->unblock(IP::fromString('123.0.0.1'));
    }

    function it_gets_all_blocked_hosts()
    {
        $hosts = $this->getBlocks();

        $hosts->shouldBeArray();
        $hosts->shouldHaveCount(2);
        $hosts->shouldContainIP(IP::fromString('123.0.0.1'));
    }

    public function getMatchers()
    {
        return [
            'containIP' => function ($array, $ip) {
                foreach ($array as $item) {
                    if ($item->equals($ip)) {
                        return true;
                    }
                }
                return false;
            }
        ];
    }
}
