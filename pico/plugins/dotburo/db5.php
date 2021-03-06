<?php

class DB5
{
    private $base_url;
    private $content_dir;
    private $isXHR;                     // Ajax
    private $json = [];                 // If isXHR, this is the data to output.
    private $is_single;                 // Boolean flag for non-home & non-xhr requests
    private $slug;                      // Path of single and xhr requests
    private $active_category;           // The category holding a single page slug
    private $is_feed;

    /**
     * Save the real config variable.
     * @param $settings
     */
    public function config_loaded(&$settings)
    {
        $this->base_url = $settings['base_url'];
        $this->content_dir = $settings['content_dir'];
    }

    /**
     * Examine the URL.
     * @param $url
     */
    public function request_url(&$url)
    {
        $this->slug = parse_url($url, PHP_URL_PATH);
        $this->isXHR = isset($_GET['get']);
        $this->is_single = !$this->isXHR && (bool)$this->slug;
        $this->is_feed = $url === 'feed';
    }

    /**
     * @return void
     */
    public function before_404_load_content ()
    {
        $this->is_single = false;
    }

    /**
     * Add meta tags.
     * @param $headers
     */
    public function before_read_file_meta(&$headers)
    {
        $headers['url'] = 'URL';
        $headers['type'] = 'Type';
        $headers['image'] = 'Image';
        $headers['category'] = 'Category';
        $headers['date_modified'] = 'Modified';
        $headers['schema_artForm'] = 'ArtForm';
        $headers['read'] = 'Read';
    }

    /**
     * Get the page's media files.
     * If xhr add meta data to the json store.
     * @param $meta
     */
    public function file_meta(&$meta)
    {
        if ($this->isXHR) {
            $this->json['meta'] = $meta;
            $this->json['meta']['media'] = $this->getMedia($meta['image']);
        } elseif ($this->is_single) {
            $meta['slug'] = $this->slug;
            $meta['media'] = $this->getMedia($meta['image']);
            $meta['image'] = $this->getImagePath($this->slug, $meta['image']);
        }
    }

    /**
     * Adds the parsed content to the json store.
     * @param $content
     */
    public function after_parse_content(&$content)
    {
        $content = trim($content);
        if ($this->isXHR) {
            $this->json['content'] = $content;
            $this->echoJson();
        }
    }

    /**
     * Set page data.
     * @param $data
     * @param $page_meta
     */
    public function get_page_data(&$data, $page_meta)
    {
        $data['date_modified'] = !empty($page_meta['date_modified']) ? $page_meta['date_modified'] : $data['date'];
        $data['slug'] = parse_url($data['url'], PHP_URL_PATH);

        if (strpos($data['slug'], '/blog/') !== false) {

            # add a trailing slash to blog paths, they don't have one
            $data['slug'] = $data['slug'] . '/';
            $data['read'] = empty($page_meta['read']) || $page_meta['read'] === "0" ? 'no' : 'yes';
        } else {
            $data['type'] = !empty($page_meta['type']) ? $page_meta['type'] : null;
            $data['category'] = !empty($page_meta['category']) ? $page_meta['category'] : null;
        }

        $data['image'] = $this->getImagePath($data['slug'], $page_meta['image']);
    }

    /**
     * If XHR echo out the json store (before before_render = before twig processing);
     * else chunk $pages based on the categories.
     * If single page request, store the category of the page for markup.
     * @param $pages
     */
    public function get_pages(&$pages)
    {
        if ($this->is_feed) {
            return;
        }

        # set the category/blog if we are on a single page
        if ($this->is_single) {
            if (strpos($this->slug, 'blog') !== false) {
                $this->active_category = 'blog';
            } else {
                $slug = "/{$this->slug}/";
                foreach ($pages as $page) {
                    if ($slug === $page['slug']) {
                        $this->active_category = $page['category'];
                        break;
                    }
                }
            }
        }

        # get the json content and alter $pages
        $pages = [
            'online' => array_filter($pages, function ($page) {
                return isset($page['category']) && $page['category'] === 'online';
            }),
            'offline' => array_filter($pages, function ($page) {
                return isset($page['category']) && $page['category'] === 'offline';
            }),
            'blog' => array_filter($pages, function ($page) {
                return strpos($page['slug'], '/blog/') !== false;
            })
        ];
    }

    /**
     * Add some global data,
     * trim down meta.media to media.
     * @param $twig_vars
     */
    public function before_render(&$twig_vars)
    {
        $twig_vars['is_single'] = $this->is_single;
        $twig_vars['slug'] = "/{$this->slug}/";
        $twig_vars['active_category'] = $this->active_category;
    }

    /**
     * Construct path for a media.
     * @param $path
     * @param string $append
     * @return null|string
     */
    protected function getImagePath($path, $append = '')
    {
        return $append
            ? ('/' . $this->content_dir . trim($path, '/') . "/media/$append")
            : null;
    }

    /**
     * Set headers and die.
     */
    protected function echoJson()
    {
        $json = json_encode($this->json);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control:	no-store, no-cache, must-revalidate');
        echo $json;
        exit;
    }

    /**
     * @param $exclude string filename
     * @return array
     */
    protected function getMedia($exclude)
    {
        $return = [];
        $baseURL = $this->base_url . '/';
        $path = $this->content_dir . $this->slug;

        if (!is_dir("$path/media")) return $return;

        $exclude = '/^((?!(\-posterframe\.)' . ($exclude ? '|(' . preg_quote($exclude) . ')' : '') . ').)*$/';
        $images = preg_grep($exclude, glob("$path/media/{*.jpeg,*.jpg,*.png,*.gif}", GLOB_BRACE));
        $videos = glob("$path/media/{*.mp4,*.webm,*.ogv}", GLOB_BRACE);
        $prevVideo = '';

        # loop through the images, build their URL
        foreach ($images as $media) {
            $return[] = [
                'src' => $baseURL . $media
            ];
        }

        # set small video formats ending with '-1280' as last
        if (is_array($videos)) {
            $videos = array_reverse($videos);
        }

        # loop through the videos
        foreach ($videos as $media) {
            $path_parts = pathinfo($media);
            $base_name = $path_parts['filename'];
            $src = $baseURL . $media;

            $pos = strrpos($base_name, '-1280');
            $name = $pos ? substr($base_name, 0, $pos) : $base_name;

            if ($name === $prevVideo) {
                $video = array_pop($return);
                $video['src'][] = [
                    'url' => $src,
                    'mime' => 'video/' . $path_parts['extension'],
                ];
                array_push($return, $video);
            } else {
                $return[] = [
                    'src' => [[
                        'url' => $src,
                        'mime' => 'video/' . $path_parts['extension'],
                    ]],
                    'poster' => $this->getPosterFrame($name, $path_parts['dirname'], $src)
                ];
                $prevVideo = $name;
            }
        }

        # alphabetical sorting
        usort($return, function ($a, $b) {
            return strcasecmp(
                !is_array($a['src']) ? $a['src'] : $a['src'][0]['url'],
                !is_array($b['src']) ? $b['src'] : $b['src'][0]['url']
            );
        });

        return $return;
    }

    /**
     * Is there a video poster frame.
     * @param $name
     * @param $folder
     * @param $videoURL
     * @return string
     */
    protected function getPosterFrame($name, $folder, $videoURL)
    {
        if (file_exists("$folder/$name-posterframe.jpg")) {
            return pathinfo($videoURL, PATHINFO_DIRNAME) . "/$name-posterframe.jpg";
        }
        if (file_exists("$folder/$name-posterframe.png")) {
            return pathinfo($videoURL, PATHINFO_DIRNAME) . "/$name-posterframe.png";
        }
        return '';
    }
}