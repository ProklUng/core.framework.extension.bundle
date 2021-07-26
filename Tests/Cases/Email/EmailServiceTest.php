<?php

namespace Prokl\CustomFrameworkExtensionsBundle\Tests\Cases\Email;

use LogicException;
use Mockery;
use Prokl\CustomFrameworkExtensionsBundle\Services\Mailer\EmailService;
use Prokl\TestingTools\Base\BaseTestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\BodyRendererInterface;

/**
 * Class EmailServiceTest
 * @package Prokl\CustomFrameworkExtensionsBundle\Tests\Cases\Email
 * @coversDefaultClass EmailService
 *
 * @since 02.11.2020
 */
class EmailServiceTest extends BaseTestCase
{
    /**
     * @var EmailService $obTestObject Тестируемый объект.
     */
    protected $obTestObject;

    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->obTestObject = new EmailService(
            $this->getMockMailerInterface(),
            $this->getMockBodyRenderer()
        );
    }

    /**
     * Метод send(). Незаданные параметры.
     *
     * @return void
     * @throws TransportExceptionInterface
     */
    public function testSendNoParameters() : void
    {
        $this->willSeeException(
            LogicException::class,
            'Email params not initialized.'
        );

        $this->obTestObject->send();
    }

    /**
     * Метод send().
     *
     * @return void
     * @throws TransportExceptionInterface
     */
    public function testSend() : void
    {
        $this->obTestObject = new EmailService(
            $this->getMockMailerInterface(1),
            $this->getMockBodyRenderer(1)
        );

        $this->obTestObject->setTemplate($this->faker->slug);
        $this->obTestObject->setEmail(
            new TemplatedEmail()
        );

        $result = $this->obTestObject->send();

        $this->assertTrue(
            $result
        );
    }

    /**
     * setEmail().
     */
    public function testSetEmail() : void
    {
        $this->checkSetter(
            'setEmail',
            'email',
            new TemplatedEmail()
        );
    }

    /**
     * setTemplate().
     */
    public function testSetTemplate() : void
    {
        $this->checkSetter(
            'setTemplate',
            'template',
            'test.template'
        );
    }

    /**
     * setContext().
     */
    public function testSetContext() : void
    {
        $this->checkSetter(
            'setContext',
            'context',
            ['test' => 123]
        );
    }

    /**
     * Мок MailerInterface.
     *
     * @param integer $times Сколько раз должен быть вызван.
     *
     * @return mixed
     */
    private function getMockMailerInterface(int $times = 0)
    {
        return Mockery::mock(
            MailerInterface::class
        )
            ->shouldReceive('send')
            ->times($times)
            ->getMock();
    }

    /**
     * Мок BodyRenderer.
     *
     * @param integer $times Сколько раз должен быть вызван.
     *
     * @return mixed
     */
    private function getMockBodyRenderer(int $times = 0)
    {
        return Mockery::mock(
            BodyRendererInterface::class
        )
            ->shouldReceive('render')
            ->times($times)
            ->getMock();
    }
}
