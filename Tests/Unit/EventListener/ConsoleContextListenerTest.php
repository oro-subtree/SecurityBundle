<?php

namespace Oro\Bundle\SecurityBundle\Tests\Unit\EventListener;

use Oro\Bundle\SecurityBundle\Authentication\Token\ConsoleToken;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Security\Core\SecurityContextInterface;

use Oro\Bundle\SecurityBundle\EventListener\ConsoleContextListener;
use Oro\Bundle\OrganizationBundle\Entity\Organization;
use Oro\Bundle\UserBundle\Entity\User;

class ConsoleContextListenerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $userRepository;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $organizationRepository;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|SecurityContextInterface
     */
    protected $securityContext;

    /**
     * @var ConsoleContextListener
     */
    protected $listener;

    protected function setUp()
    {
        $this->securityContext = $this->getMockBuilder('Symfony\Component\Security\Core\SecurityContext')
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $this->userRepository = $this->getMock('Doctrine\Common\Persistence\ObjectRepository');
        $this->organizationRepository = $this->getMock('Doctrine\Common\Persistence\ObjectRepository');

        $registry = $this->getMock('Doctrine\Common\Persistence\ManagerRegistry');
        $registry->expects($this->any())
            ->method('getRepository')
            ->with($this->isType('string'))
            ->will(
                $this->returnCallback(
                    function ($entity) {
                        switch ($entity) {
                            case 'OroUserBundle:User':
                                return $this->userRepository;
                            case 'OroOrganizationBundle:Organization':
                                return $this->organizationRepository;
                        }
                        return null;
                    }
                )
            );

        $this->listener = new ConsoleContextListener($registry, $this->securityContext);
    }

    public function testNoOptions()
    {
        $this->assertEmpty($this->securityContext->getToken());

        $event = $this->getEvent();
        $this->listener->onConsoleCommand($event);

        $this->assertEmpty($this->securityContext->getToken());
    }

    public function testSetUserIdAndOrganizationIds()
    {
        $this->assertEmpty($this->securityContext->getToken());

        $userId = 1;
        $user = new User();
        $user->setId($userId);

        $organizationId = 2;
        $organization = new Organization();
        $organization->setId($organizationId);

        $event = $this->getEvent();
        /** @var \PHPUnit_Framework_MockObject_MockObject  $input */
        $input = $event->getInput();
        $input->expects($this->at(1))
            ->method('getOption')
            ->with(ConsoleContextListener::OPTION_USER)
            ->will($this->returnValue($userId));
        $input->expects($this->at(2))
            ->method('getOption')
            ->with(ConsoleContextListener::OPTION_ORGANIZATION)
            ->will($this->returnValue($organizationId));

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with($userId)
            ->will($this->returnValue($user));
        $this->organizationRepository->expects($this->once())
            ->method('find')
            ->with($organizationId)
            ->will($this->returnValue($organization));

        $this->listener->onConsoleCommand($event);
        /** @var ConsoleToken $token */
        $token = $this->securityContext->getToken();

        $this->assertNotEmpty($token);
        $this->assertInstanceOf('Oro\Bundle\SecurityBundle\Authentication\Token\ConsoleToken', $token);
        $this->assertEquals($user, $token->getUser());
        $this->assertEquals($organization, $token->getOrganizationContext());
    }

    public function testSetUsernameAndOrganizationName()
    {
        $this->assertEmpty($this->securityContext->getToken());

        $username = 'test_user';
        $user = new User();
        $user->setUsername($username);

        $organizationName = 'test_organization';
        $organization = new Organization();
        $organization->setName($organizationName);

        $event = $this->getEvent();
        /** @var \PHPUnit_Framework_MockObject_MockObject  $input */
        $input = $event->getInput();
        $input->expects($this->at(1))
            ->method('getOption')
            ->with(ConsoleContextListener::OPTION_USER)
            ->will($this->returnValue($username));
        $input->expects($this->at(2))
            ->method('getOption')
            ->with(ConsoleContextListener::OPTION_ORGANIZATION)
            ->will($this->returnValue($organizationName));

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $username])
            ->will($this->returnValue($user));
        $this->organizationRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $organizationName])
            ->will($this->returnValue($organization));

        $this->listener->onConsoleCommand($event);
        /** @var ConsoleToken $token */
        $token = $this->securityContext->getToken();

        $this->assertNotEmpty($token);
        $this->assertInstanceOf('Oro\Bundle\SecurityBundle\Authentication\Token\ConsoleToken', $token);
        $this->assertEquals($user, $token->getUser());
        $this->assertEquals($organization, $token->getOrganizationContext());
    }

    /**
     * @return ConsoleCommandEvent
     */
    protected function getEvent()
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|InputDefinition $application */
        $definition = $this->getMockBuilder('Symfony\Component\Console\Input\InputDefinition')
            ->disableOriginalConstructor()
            ->setMethods(['addOption', 'getOption'])
            ->getMock();
        $definition->expects($this->at(0))
            ->method('addOption')
            ->with($this->isInstanceOf('Symfony\Component\Console\Input\InputOption'))
            ->will(
                $this->returnCallback(
                    function (InputOption $option) {
                        $this->assertEquals(ConsoleContextListener::OPTION_USER, $option->getName());
                    }
                )
            );
        $definition->expects($this->at(1))
            ->method('addOption')
            ->with($this->isInstanceOf('Symfony\Component\Console\Input\InputOption'))
            ->will(
                $this->returnCallback(
                    function (InputOption $option) {
                        $this->assertEquals(ConsoleContextListener::OPTION_ORGANIZATION, $option->getName());
                    }
                )
            );

        /** @var \PHPUnit_Framework_MockObject_MockObject|Application $application */
        $application = $this->getMockBuilder('Symfony\Component\Console\Application')
            ->disableOriginalConstructor()
            ->setMethods(['getHelperSet'])
            ->getMock();
        $application->setDefinition($definition);
        $application->expects($this->any())
            ->method('getHelperSet')
            ->will($this->returnValue(new HelperSet()));

        /** @var \PHPUnit_Framework_MockObject_MockObject|Command $command */
        $command = $this->getMockBuilder('Symfony\Component\Console\Command\Command')
            ->disableOriginalConstructor()
            ->setMethods(['mergeApplicationDefinition'])
            ->getMock();
        $command->setApplication($application);
        $command->expects($this->once())
            ->method('mergeApplicationDefinition');

        $input = $this->getMock('Symfony\Component\Console\Input\InputInterface');
        $input->expects($this->once())
            ->method('bind')
            ->with($definition);

        $output = $this->getMock('Symfony\Component\Console\Output\OutputInterface');

        return new ConsoleCommandEvent($command, $input, $output);
    }
}
