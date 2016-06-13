<?php

namespace Oro\Bundle\SecurityBundle\Tests\Behat\Context;

use Behat\Behat\Hook\Scope\AfterStepScope;
use Behat\Behat\Hook\Scope\BeforeStepScope;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Doctrine\Common\Inflector\Inflector;
use Oro\Bundle\TestFrameworkBundle\Behat\Element\OroElementFactory;
use Oro\Bundle\TestFrameworkBundle\Behat\Element\OroElementFactoryAware;
use Oro\Bundle\TestFrameworkBundle\Tests\Behat\Context\WaitingDictionary;
use Oro\Bundle\UserBundle\Tests\Behat\Element\UserRoleForm;

class ACLContext extends RawMinkContext implements OroElementFactoryAware
{
    use WaitingDictionary;

    /**
     * @var OroElementFactory
     */
    protected $elementFactory;

    /**
     * @param OroElementFactory $elementFactory
     *
     * @return null
     */
    public function setElementFactory(OroElementFactory $elementFactory)
    {
        $this->elementFactory = $elementFactory;
    }

    /**
     * @Given /^I am logged in under (?P<organization>(\D*)) organization$/
     */
    public function iAmLoggedInUnderSystemOrganization($organization)
    {
        $page = $this->getSession()->getPage();
        $page->find('css', '.btn-organization-switcher')->click();
        $page->find('css', '.dropdown-organization-switcher')->clickLink($organization);
    }

    /**
     * @Given /^(?:|I )have "(?P<accessLevel>(?:[^"]|\\")*)" permissions for "(?P<action>(?:[^"]|\\")*)" "(?P<entity>(?:[^"]|\\")*)" entity$/
     * @Given /^my permissions on (?P<action1>(?:|View|Create|Edit|Delete|Assign|Share)) (?P<entity>(?:[^"]|\\")*) is set to (?P<accessLevel>(?:[^"]|\\")*)$/
     * @When /^(?:|I )set my permissions on (?P<action1>(?:|View|Create|Edit|Delete|Assign|Share)) (?P<entity>(?:[^"]|\\")*) to (?P<accessLevel>(?:[^"]|\\")*)$/
     */
    public function iHavePermissionsForEntity($entity, $action, $accessLevel)
    {
        $this->getMink()->setDefaultSessionName('second_session');
        $this->getSession()->resizeWindow(1920, 1080, 'current');

        $singularizedEntity = ucfirst(Inflector::singularize($entity));
        $this->loginAsAdmin();
        $this->openRoleEditForm('Administrator');
        /** @var UserRoleForm $userRoleForm */
        $userRoleForm = $this->elementFactory->createElement('UserRoleForm');
        $userRoleForm->setPermission($singularizedEntity, $action, $accessLevel);
        $userRoleForm->saveAndClose();
        $this->waitForAjax();

        $this->getSession('second_session')->stop();
        $this->getMink()->setDefaultSessionName('first_session');
    }

    /**
     * @Given /^my permissions on (?P<action1>(?:|View|Create|Edit|Delete|Assign|Share)) (?P<entity>(?:[^"]|\\")*) as (?P<accessLevel1>(?:[^"]|\\")*) and on (?P<action2>(?:|View|Create|Edit|Delete|Assign|Share)) as (?P<accessLevel2>(?:[^"]|\\")*)$/
     */
    public function iHaveSeveralPermissionsForEntity($entity, $action1, $accessLevel1, $action2, $accessLevel2)
    {
        $this->getMink()->setDefaultSessionName('second_session');
        $this->getSession()->resizeWindow(1920, 1080, 'current');

        $singularizedEntity = ucfirst(Inflector::singularize($entity));
        $this->loginAsAdmin();
        $this->openRoleEditForm('Administrator');
        /** @var UserRoleForm $userRoleForm */
        $userRoleForm = $this->elementFactory->createElement('UserRoleForm');
        $userRoleForm->setPermission($singularizedEntity, $action1, $accessLevel1);
        $userRoleForm->setPermission($singularizedEntity, $action2, $accessLevel2);
        $userRoleForm->saveAndClose();
        $this->waitForAjax();

        $this->getSession('second_session')->stop();
        $this->getMink()->setDefaultSessionName('first_session');
    }

    /**
     * @Then only my records should be deleted
     */
    public function onlyMyRecordsShouldBeDeleted()
    {
        throw new PendingException();
    }

    protected function loginAsAdmin()
    {
        $this->visitPath('/user/login');
        $login = $this->elementFactory->createElement('Login');
        $login->fill(new TableNode([['Username', 'admin'], ['Password', 'admin']]));
        $login->pressButton('Log in');
        $this->waitForAjax();
    }

    /**
     * @param $role
     */
    protected function openRoleEditForm($role)
    {
        $this->elementFactory->createElement('MainMenu')->openAndClick('System -> User Management', 'Roles');
        $this->waitForAjax();
        $this->elementFactory->createElement('Grid')->clickActionLink($role, 'Edit');
        $this->waitForAjax();
    }
}
