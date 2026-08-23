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
        "Date": "Thu, 21 May 2020 15:35:32 +0000",
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

    private static string $jsonEmailWithDateAsList = <<<JSON
[
  {
    "ID": 1,
    "Content": {
      "Headers": {
        "Subject": "An email",
        "Date": [
          "Thu, 21 May 2020 15:35:32 +0000"
        ],
        "From": "from@test.com",
        "To": [
          "to@test.com"
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

    /**
     * @param mixed $date Value of the "Date" header of the first email, null to omit the header
     * @param array $expectedIds Expected IDs in the resulting inbox
     */
    #[DataProvider('dataDateHeader')]
    public function testDateHeaderDeterminesOrder($date, array $expectedIds): void
    {
        $this->fetchEmailsFrom(self::emailsJson([1 => $date, 2 => 'Thu, 21 May 2020 15:35:32 +0000']));

        self::assertSame($expectedIds, $this->inboxIds());
    }

    public static function dataDateHeader(): iterable
    {
        // the second email of the fixture is always "Thu, 21 May 2020 15:35:32 +0000"
        yield 'two digit day, newer' => ['Fri, 22 May 2020 15:35:32 +0000', [1, 2]];

        yield 'single digit day, older' => ['Fri, 1 May 2020 15:35:32 +0000', [2, 1]];

        yield 'named time zone' => ['Fri, 22 May 2020 15:35:32 GMT', [1, 2]];

        yield 'header returned as a list' => [['Fri, 22 May 2020 15:35:32 +0000'], [1, 2]];

        // the weekday name is stripped before parsing, so a mismatching one does not shift the date
        yield 'mismatching weekday name' => ['Mon, 22 May 2020 15:35:32 +0000', [1, 2]];

        yield 'missing sign in the UTC offset' => ['Fri, 22 May 2020 15:35:32 0000', [2, 1]];

        yield 'empty value' => ['', [2, 1]];

        yield 'uninterpretable value' => ['not a date at all', [2, 1]];

        yield 'missing header' => [null, [2, 1]];
    }

    public function testFetchEmailsSortsNewestFirst(): void
    {
        $this->fetchEmailsFrom(self::emailsJson([
            1 => 'Thu, 21 May 2020 15:35:32 +0000',
            2 => 'Sat, 23 May 2020 15:35:32 +0000',
            3 => 'Fri, 22 May 2020 15:35:32 +0000',
        ]));

        self::assertSame([2, 3, 1], $this->inboxIds());
        self::assertSame([2, 3, 1], $this->unreadInboxIds());
    }

    public function testFetchEmailsComparesInstantsNotHeaderText(): void
    {
        $this->fetchEmailsFrom(self::emailsJson([
            1 => 'Thu, 21 May 2026 08:00:00 +0000',
            2 => 'Fri, 2 Jun 2026 08:00:00 +0200',
        ]));

        self::assertSame([2, 1], $this->inboxIds());
    }

    public function testFetchEmailsSortsByInstantNotLocalTime(): void
    {
        // the later local time is the earlier instant
        $this->fetchEmailsFrom(self::emailsJson([
            1 => 'Thu, 21 May 2020 11:00:00 +0200',
            2 => 'Thu, 21 May 2020 10:00:00 +0000',
        ]));

        self::assertSame([2, 1], $this->inboxIds());
    }

    public function testFetchEmailsPutsEmailWithoutUsableDateLast(): void
    {
        $this->fetchEmailsFrom(self::emailsJson([
            1 => null,
            2 => 'Thu, 21 May 2020 15:35:32 +0000',
            3 => 'Fri, 22 May 2020 15:35:32 +0000',
        ]));

        self::assertSame([3, 2, 1], $this->inboxIds());
    }

    public function testFetchEmailsKeepsServerOrderWhenNoDateIsUsable(): void
    {
        $this->fetchEmailsFrom(self::emailsJson([1 => null, 2 => '', 3 => 'not a date at all']));

        self::assertSame([1, 2, 3], $this->inboxIds());
    }

    public function testFetchEmailsWithEmptyInbox(): void
    {
        $this->fetchEmailsFrom(self::emailsJson([]));

        self::assertSame([], $this->inboxIds());
        self::assertSame([], $this->unreadInboxIds());
    }

    public function testFetchEmailsWithSingleEmail(): void
    {
        $this->fetchEmailsFrom(self::emailsJson([1 => 'Thu, 21 May 2020 15:35:32 +0000']));

        self::assertSame([1], $this->inboxIds());
    }

    public function testFetchEmailsWithDateHeaderAsList(): void
    {
        $this->fetchEmailsFrom(self::$jsonEmailWithDateAsList);

        self::assertEquals(json_decode(self::$jsonEmailWithDateAsList, false), $this->mailHog->getCurrentInbox());
    }

    public function testOpenNextUnreadEmailFollowsInboxOrder(): void
    {
        $json = self::emailsJson([
            1 => 'Thu, 21 May 2020 15:35:32 +0000',
            2 => 'Sat, 23 May 2020 15:35:32 +0000',
            3 => 'Fri, 22 May 2020 15:35:32 +0000',
        ]);

        $this->mailHog->setClient($this->createInboxClient($json));
        $this->mailHog->fetchEmails();

        $openedIds = [];
        for ($i = 0; $i < 3; ++$i) {
            $this->mailHog->openNextUnreadEmail();
            $openedIds[] = $this->mailHog->getPropOpenedEmail()->ID;
        }

        self::assertSame([2, 3, 1], $openedIds);
        self::assertSame([], $this->mailHog->getUnreadInbox());
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

    /**
     * @param array $emails Map of email ID to the value of the "Date" header, null omits the header
     *
     * @return string JSON
     */
    private static function emailsJson(array $emails): string
    {
        $messages = [];
        foreach ($emails as $id => $date) {
            $headers = [
                'Subject' => ['An email'],
                'From' => ['from@test.com'],
                'To' => ['to@test.com'],
            ];
            if ($date !== null) {
                $headers['Date'] = $date;
            }
            $messages[] = ['ID' => $id, 'Content' => ['Headers' => $headers, 'Body' => '']];
        }

        return json_encode($messages, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $json JSON returned by the capture server
     */
    private function fetchEmailsFrom(string $json): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getBody')->willReturn(Utils::streamFor($json));
        $client = $this->createStub(Client::class);
        $client->method('request')->willReturn($response);

        $this->mailHog->setClient($client);
        $this->mailHog->fetchEmails();
    }

    /**
     * @param string $json JSON of the message list
     *
     * @return ClientInterface Stubbed client
     */
    private function createInboxClient(string $json): ClientInterface
    {
        $messagesById = [];
        foreach (json_decode($json, false) as $message) {
            $messagesById[$message->ID] = $message;
        }

        $client = $this->createStub(Client::class);
        $client->method('request')->willReturnCallback(
            function (string $method, string $uri) use ($json, $messagesById): ResponseInterface {
                $id = substr($uri, (int)strrpos($uri, '/') + 1);
                $body = $id === 'messages' ? $json : json_encode($messagesById[$id], JSON_THROW_ON_ERROR);

                $response = $this->createStub(ResponseInterface::class);
                $response->method('getBody')->willReturn(Utils::streamFor($body));

                return $response;
            }
        );

        return $client;
    }

    /**
     * @return array IDs
     */
    private function inboxIds(): array
    {
        return array_map(static function ($email) {
            return $email->ID;
        }, $this->mailHog->getCurrentInbox());
    }

    /**
     * @return array IDs
     */
    private function unreadInboxIds(): array
    {
        return array_map(static function ($email) {
            return $email->ID;
        }, $this->mailHog->getUnreadInbox());
    }
}
