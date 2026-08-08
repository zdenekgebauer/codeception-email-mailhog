<?php

namespace Codeception\Module\Tests;

use Codeception\Lib\ModuleContainer;
use Codeception\Module\MailHog;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class MailHogTest extends TestCase
{
    private const URL = 'http://test';
    private const PORT = 8888;

    private static string $jsonEmail = <<<JSON
[
  {
    "ID": 1,
    "Content": {
      "Headers": {
        "Subject": "An email",
        "Date": "Thu, 21 May 2020 15:35:32 0000",
        "From": "from@test.com",
        "To": [
          "to@test.com"
        ],
        "Cc": [
            "carbon@copy.ru"
        ],
        "Bcc": [
            "blind-carbon@copy.ru"
        ],
        "Sender": "sender@test.com",
        "Reply": "reply-to@test.com"
      },
      "From": "From...",
      "Body": {
        "type": "text/html",
        "content": {
          "id": "id",
          "type": "text/html",
          "text": "<bold>Dear Testet</bold>, <br/>This is a test."
        }
      }
    }
  }
]
JSON;

    private MailHog $mailHog;

    public function testFetchEmailsPositive(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::atLeastOnce())
            ->method('getBody')
            ->willReturn(Utils::streamFor(self::$jsonEmail));
        $client = $this->createMock(Client::class);
        $client->expects(self::atLeastOnce())
            ->method('request')
            ->with('GET', '/api/v1/messages')
            ->willReturn($response);

        $this->mailHog->setClient($client);
        $this->mailHog->fetchEmails();

        $this->assertEquals(json_decode(self::$jsonEmail, false), $this->mailHog->getCurrentInbox());
        $this->assertEquals(json_decode(self::$jsonEmail, false), $this->mailHog->getUnreadInbox());
    }

    public function testFetchEmailsNegative(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::atLeastOnce())
            ->method('request')
            ->with('GET', '/api/v1/messages')
            ->willThrowException(new \Exception('Test exception'));

        $this->mailHog->setClient($client);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Exception: Test exception');

        $this->mailHog->fetchEmails();
    }

    /**
     * @param string $address
     * @param string $emails
     * @param array $inbox
     */
    #[DataProvider('dataAccessInboxFor')]
    public function testAccessInboxFor(string $address, string $emails, array $inbox): void
    {
        $this->mailHog->setFetchedEmails($emails);
        $this->mailHog->accessInboxFor($address);

        self::assertEquals($inbox, $this->mailHog->getCurrentInbox());
        self::assertEquals($inbox, $this->mailHog->getUnreadInbox());
    }

    public static function dataAccessInboxFor(): iterable
    {
        $address1 = 'other-address@mail.ru';
        $email = self::$jsonEmail;
        $decodedEmail = json_decode($email, false);

        yield 'Mismatch address in "To", "Cc" and "Bcc"' => [$address1, $email, []];

        $address2 = 'to@test.com';

        yield 'Match "To" only' => [$address2, $email, $decodedEmail];

        $address3 = 'carbon@copy.ru';

        yield 'Match "Cc" only' => [$address3, $email, $decodedEmail];

        $address4 = 'blind-carbon@copy.ru';

        yield 'Match "Bcc" only' => [$address4, $email, $decodedEmail];
    }

    /**
     * @param string $address
     * @param string $emails
     * @param array $inbox
     */
    #[DataProvider('dataAccessInboxForTo')]
    public function testAccessInboxForTo(string $address, string $emails, array $inbox): void
    {
        $this->mailHog->setFetchedEmails($emails);
        $this->mailHog->accessInboxForTo($address);

        self::assertEquals($inbox, $this->mailHog->getCurrentInbox());
        self::assertEquals($inbox, $this->mailHog->getUnreadInbox());
    }

    public static function dataAccessInboxForTo(): iterable
    {
        $address1 = 'other-address@mail.ru';
        $email = self::$jsonEmail;
        $decodedEmail = json_decode($email, false);

        yield 'Mismatch address in "To"' => [$address1, $email, []];

        $address2 = 'to@test.com';

        yield 'Match "To"' => [$address2, $email, $decodedEmail];
    }

    /**
     * @param string $address
     * @param string $emails
     * @param array $inbox
     */
    #[DataProvider('dataAccessInboxForCc')]
    public function testAccessInboxForCc(string $address, string $emails, array $inbox): void
    {
        $this->mailHog->setFetchedEmails($emails);
        $this->mailHog->accessInboxForCc($address);

        self::assertEquals($inbox, $this->mailHog->getCurrentInbox());
        self::assertEquals($inbox, $this->mailHog->getUnreadInbox());
    }

    public static function dataAccessInboxForCc(): iterable
    {
        $address1 = 'other-address@mail.ru';
        $email = self::$jsonEmail;
        $decodedEmail = json_decode($email, false);

        yield 'Mismatch address in "Cc"' => [$address1, $email, []];

        $address2 = 'carbon@copy.ru';

        yield 'Match "Cc"' => [$address2, $email, $decodedEmail];
    }

    /**
     * @param string $address
     * @param string $emails
     * @param array $inbox
     */
    #[DataProvider('dataAccessInboxForBcc')]
    public function testAccessInboxForBcc(string $address, string $emails, array $inbox): void
    {
        $this->mailHog->setFetchedEmails($emails);
        $this->mailHog->accessInboxForBcc($address);

        self::assertEquals($inbox, $this->mailHog->getCurrentInbox());
        self::assertEquals($inbox, $this->mailHog->getUnreadInbox());
    }

    public static function dataAccessInboxForBcc(): iterable
    {
        $address1 = 'other-address@mail.ru';
        $email = self::$jsonEmail;
        $decodedEmail = json_decode($email, false);

        yield 'Mismatch address in "Cc"' => [$address1, $email, []];

        $address2 = 'blind-carbon@copy.ru';

        yield 'Match "Bcc"' => [$address2, $email, $decodedEmail];
    }

    public function testDeleteAllEmailsPositive(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $client = $this->createMock(Client::class);
        $client->expects(self::atLeastOnce())
            ->method('request')
            ->with('DELETE', '/api/v1/messages')
            ->willReturn($response);

        $this->mailHog->setClient($client);
        $this->mailHog->deleteAllEmails();
    }

    public function testDeleteAllEmailsNegative(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::atLeastOnce())
            ->method('request')
            ->with('DELETE', '/api/v1/messages')
            ->willThrowException(new \Exception('Test exception'));

        $this->mailHog->setClient($client);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Exception: Test exception');

        $this->mailHog->deleteAllEmails();
    }

    public function testOpenNextUnreadEmailPositive(): void
    {
        $this->mailHog->setUnreadInbox(json_decode(self::$jsonEmail, false));
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::atLeastOnce())
            ->method('getBody')
            ->willReturn(Utils::streamFor(self::$jsonEmail));
        $client = $this->createMock(Client::class);
        $client->expects(self::atLeastOnce())
            ->method('request')
            ->with('GET', '/api/v1/messages/1')
            ->willReturn($response);

        $this->mailHog->setClient($client);

        $this->mailHog->openNextUnreadEmail();

        self::assertEquals(json_decode(self::$jsonEmail, false), $this->mailHog->getPropOpenedEmail());
    }

    public function testOpenNextUnreadEmailNegative(): void
    {
        $this->mailHog->setUnreadInbox([]);

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Unread Inbox is Empty');

        $this->mailHog->openNextUnreadEmail();
    }

    public function testGrabHeaders(): void
    {
        $this->mailHog->setUnreadInbox(json_decode(self::$jsonEmail, false));
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::atLeastOnce())
            ->method('getBody')
            ->willReturn(Utils::streamFor(json_encode(json_decode(self::$jsonEmail)[0])));
        $client = $this->createMock(Client::class);
        $client->expects(self::atLeastOnce())
            ->method('request')
            ->with('GET', '/api/v1/messages/1')
            ->willReturn($response);

        $this->mailHog->setClient($client);

        $this->mailHog->openNextUnreadEmail();

        $headers = $this->mailHog->grabHeadersFromEmail();
        self::assertArrayHasKey('From', $headers);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $mockContainer = $this->createStub(ModuleContainer::class);
        $params = [
            'url' => self::URL,
            'port' => self::PORT,
            'guzzleRequestOptions' => [
                'strict' => false,
            ],
        ];

        $mailHog = new class ($mockContainer, $params) extends MailHog {
            public function setClient(ClientInterface $mailhog): void
            {
                $this->mailhog = $mailhog;
            }

            public function getCurrentInbox(): array
            {
                return $this->currentInbox;
            }

            public function getUnreadInbox(): array
            {
                return $this->unreadInbox;
            }

            public function setUnreadInbox($inbox): void
            {
                $this->unreadInbox = $inbox;
            }

            public function setFetchedEmails(string $emails): void
            {
                $this->fetchedEmails = json_decode($emails, false);
            }

            public function getPropOpenedEmail()
            {
                return $this->openedEmail;
            }
        };

        $this->mailHog = $mailHog;
        $this->mailHog->_initialize();
    }
}
