<?php

namespace Mollsoft\WebTelegramBot;

use DefStudio\Telegraph\DTO\Attachment;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Keyboard\ReplyKeyboard;
use DefStudio\Telegraph\Telegraph;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Mollsoft\WebTelegramBot\DTO\HTMLToTelegraphDTO;
use Mollsoft\WebTelegramBot\Models\TelegraphChat;
use Symfony\Component\DomCrawler\Crawler;

class HTMLToTelegraph
{
    /** @var Collection<HTMLToTelegraphDTO> */
    public readonly Collection $collection;

    protected readonly TelegraphChat $chat;
    protected Crawler $crawler;

    public function __construct(
        protected readonly Request $request,
        protected readonly Response $response
    ) {
        $this->collection = collect();
        $this->chat = $this->request->chat();
        $this->crawler = (new Crawler("<root>{$this->response->getContent()}</root>"))->filter('root > *');
    }

    /**
     * @return Collection<HTMLToTelegraphDTO>
     */
    public function run(): Collection
    {
        $this->crawler->each(function (Crawler $element) {
            if ($element->nodeName() === 'message') {
                $this->collection->add(
                    $this->createMessage($element)
                );
            }
        });

        return $this->collection;
    }

    protected function cacheAttachment(string $src, &$cacheKey)
    {
        if (File::exists($src)) {
            $checksum = hash_file('sha256', $src);
            $cacheKey = implode(':', [__CLASS__, $this->chat->telegraph_bot_id, $checksum]);
            if (Cache::has($cacheKey)) {
                $src = Cache::get($cacheKey);
                $cacheKey = null;
            }
        }

        return $src;
    }

    protected ?array $messageBody = null;

    protected function createMessage(Crawler $element): HTMLToTelegraphDTO
    {
        $lines = [];
        $telegraph = $this->chat->message('');
        $fileCacheKey = null;
        $this->messageBody = [];

        $element->children()->each(function (Crawler $element) use (&$telegraph, &$lines, &$fileCacheKey) {
            switch ($element->nodeName()) {
                case 'img':
                    $src = $element->attr('src');
                    $src = $this->cacheAttachment($src, $fileCacheKey);

                    $this->messageBody['photo'] = compact('src');
                    $telegraph = $telegraph->photo($src);
                    break;

                case 'video':
                    $src = $element->attr('src');
                    $src = $this->cacheAttachment($src, $fileCacheKey);

                    $this->messageBody['video'] = compact('src');
                    $telegraph = $telegraph->video($src);
                    break;

                case 'audio':
                    $src = $element->attr('src');

                    $this->messageBody['audio'] = compact('src');
                    $telegraph = $telegraph->audio($src);
                    break;

                case 'document':
                    $src = $element->attr('src');

                    $this->messageBody['document'] = compact('src');
                    $telegraph = $telegraph->document($src);
                    break;

                case 'reply-keyboard':
                    $telegraph = $this->replyKeyboard($telegraph, $element);
                    break;

                case 'keyboard':
                    $telegraph = $this->keyboard($telegraph, $element);
                    break;

                case 'p':
                    $html = $element->html();
                    $lines[] = trim($html);
                    break;
            }
        });

        $text = implode("\n", $lines);
        $this->messageBody['message'] = $text;
        $checksum = md5(trim($element->html()));
        $telegraph = $telegraph->message($text);

        return new HTMLToTelegraphDTO($this->messageBody, $telegraph, $checksum, $fileCacheKey);
    }

    protected function replyKeyboard(Telegraph $telegraph, Crawler $element): Telegraph
    {
        $replyKeyboard = array_filter(
            $element->children()->each(fn(Crawler $element) => match ($element->nodeName()) {
                'row' => array_filter(
                    $element->children()->each(fn(Crawler $element) => match ($element->nodeName()) {
                        'column' => [
                            'text' => ($child = $element->children()->first())->nodeName() === 'button' ? trim(
                                $child->html()
                            ) : trim($element->html()),
                        ],
                        'button' => ['text' => trim($element->html())],
                        'a' => ['text' => trim($element->html()), 'web_app' => ['url' => $element->attr('href')]],
                        default => null,
                    })
                ),
                'button' => [[['text' => trim($element->html())]]],
                default => null,
            })
        );

        if (count($replyKeyboard) > 0) {
            $this->messageBody['reply-keyboard'] = $replyKeyboard;
            $telegraph = $telegraph->replyKeyboard(
                ReplyKeyboard::fromArray(
                    $replyKeyboard
                )->resize()
            );
        }

        return $telegraph;
    }

    protected function keyboard(Telegraph $telegraph, Crawler $element): Telegraph
    {
        $keyboard = array_filter(
            $element->children()->each(fn(Crawler $element) => match ($element->nodeName()) {
                'row' => array_filter(
                    $element->children()->each(fn(Crawler $element) => match ($element->nodeName()) {
                        'column' => match (($child = $element->children()->first())->nodeName()) {
                            'button' => [
                                'text' => trim($child->html()),
                                'callback_data' => $child->attr('value') ?: 'null'
                            ],
                            'a' => $this->generateLink($child),
                            default => ['text' => trim($child->html())],
                        },
                        'button' => [
                            [
                                'text' => trim($element->html()),
                                'callback_data' => $element->attr('value') ?: 'null'
                            ]
                        ],
                        'a' => [[$this->generateLink($element)]],
                        default => null,
                    })
                ),
                'button' => [
                    [
                        [
                            'text' => trim($element->html()),
                            'callback_data' => $element->attr('value') ?: 'null'
                        ]
                    ]
                ],
                'a' => [[$this->generateLink($element)]],
                default => null,
            })
        );

        if (count($keyboard) > 0) {
            $this->messageBody['keyboard'] = $keyboard;
            $telegraph = $telegraph->keyboard(
                Keyboard::fromArray(
                    $keyboard
                )
            );
        }

        return $telegraph;
    }

    protected function generateLink(Crawler $element): array
    {
        $data = [
            'text' => trim($element->html()),
        ];

        if ($element->attr('target') === '_blank') {
            $data['url'] = $element->attr('href');
        } else {
            $uuid = Str::uuid();
            Cache::set($uuid, $element->attr('href'), now()->addHour());
            $data['callback_data'] = 'action:link;href:'.$uuid;
        }

        return $data;
    }
}
