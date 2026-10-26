<?php
namespace Wbengine\Content\Migration;
final readonly class Conversion implements \JsonSerializable
{
    public function __construct(public string $markdown, public string $sourceHash, public array $issues = []) {}
    public function safe(): bool { return $this->issues === []; }
    public function jsonSerialize(): array { return ['markdown' => $this->markdown, 'source_hash' => $this->sourceHash, 'safe' => $this->safe(), 'issues' => $this->issues]; }
}
