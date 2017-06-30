<?php

namespace App\Content;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use League\Flysystem\Adapter\Local;
use League\Flysystem\FileNotFoundException;
use League\Flysystem\Filesystem;
use Spatie\YamlFrontMatter\Parser;
use Symfony\Component\Yaml\Yaml;

class ContentRepository
{
    /** @var \League\Flysystem\Filesystem */
    private $filesystem;

    /** @var \Spatie\YamlFrontMatter\Parser */
    private $frontMatterParser;

    /** @var \Symfony\Component\Yaml\Yaml */
    private $yamlParser;

    public function __construct()
    {
        $this->filesystem = new Filesystem(
            new Local(base_path('content'))
        );

        $this->frontMatterParser = new Parser();
        $this->yamlParser = new Yaml();
    }

    public function posts(): Collection
    {
        return collect($this->filesystem->listContents('posts'))
            ->filter(function (array $item) {
                return $item['type'] === 'dir';
            })
            ->flatMap(function (array $item) {
                return $this->filesystem->listContents($item['path']);
            })
            ->pluck('path')
            ->map(function(string $path) {
                $slug = str_replace_first('posts/', '', $path);
                $slug = str_replace_last('.md', '', $slug);

                return $this->post($slug);
            })
            ->sort(function (Post $a, Post $b) {
                return $a->date->getTimeStamp() < $b->date->getTimeStamp();
            });
    }

    public function post(string $slug): Post
    {
        $rawFile = $this->read('posts/'.$slug.'.md');

        if (! $rawFile) {
            throw new ModelNotFoundException("Post `{$slug}` not found");
        }

        return Post::create(
            $this->frontMatterParser->parse($rawFile),
            $slug
        );
    }

    public function articles(): Collection
    {
        return $this->posts();
    }

    public function openSource(): Collection
    {
        return $this->yaml('open-source.yaml')
            ->map([Project::class, 'create'])
            ->sortBy('name');
    }

    public function blogroll(): Collection
    {
        return $this->yaml('blogroll.yaml')
            ->map([BlogrollItem::class, 'create'])
            ->sortBy('name');
    }

    public function feed(): Collection
    {
        return $this->posts()->map([FeedItem::class, 'fromPost']);
    }

    private function yaml(string $path): Collection
    {
        return collect($this->yamlParser->parse(
            $this->read($path)
        ));
    }

    private function read(string $path): string
    {
        try {
            return $this->filesystem->read($path);
        } catch (FileNotFoundException $e) {
            return '';
        }
    }
}
