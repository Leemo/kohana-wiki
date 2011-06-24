# Model preparing

All these difficulties are made only in order to provide
the flexibility of this module.

## Multiple wikis and scope row

This module allows to store multiple wikis in one table.
For example in situation like that: `projects/<some_project_id>/wiki/...`

Do you understand me? Ok... To solve this problem use the scope row.

~~~
$wiki = ORM:factory('wiki')
  ->scope('some_project_id');
~~~

The scope can be either numeric or string. By default - it is __NULL__;

## Internal wiki url

It is also necessary to specify the format of internal links.
This is done as follows:

~~~
// :page will be replaced by the wiki page name
$url = Route::get('default')
  ->uri(array(
    'controller' => 'wiki',
    'action'     => 'view',
    'id'         => ':page'
  ));

$wiki = ORM::factory('wiki')
  ->local_url($url);
~~~



## Image url

Another problem - the images. More precisely, the paths to them.

~~~
// :image will be replaced by the image name
$url = Route::get('default')
  ->uri(array(
    'controller' => 'wiki',
    'action'     => 'images',
    'id'         => ':image'
  ));

$wiki = ORM::factory('wiki')
  ->image_url($url);
~~~