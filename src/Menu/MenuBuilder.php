<?php

   namespace App\Menu;

   use Knp\Menu\FactoryInterface;
   use Knp\Menu\ItemInterface;

   class MenuBuilder
   {
       private $factory;

       public function __construct(FactoryInterface $factory)
       {
           $this->factory = $factory;
       }

       public function createMainMenu(array $options): ItemInterface
       {
           $menu = $this->factory->createItem('root');
           $menu->setChildrenAttribute('class', 'nav flex-column');

           $menu->addChild('Dashboard', ['uri' => '#', 'attributes' => ['class' => 'nav-item']])
               ->setLinkAttribute('class', 'nav-link text-white')
               ->setExtra('icon', 'fa fa-home mr-2');

           $menu->addChild('Activity', ['route' => 'admin_activity_admin_manage', 'attributes' => ['class' => 'nav-item']])
               ->setLinkAttribute('class', 'nav-link text-white')
               ->setLinkAttribute('style', 'background-color: #007bff; border-radius: 5px; margin: 5px 0; padding: 10px;')
               ->setExtra('icon', 'fa fa-list-alt mr-2');

           $menu->addChild('Category', ['route' => 'admin_category_index', 'attributes' => ['class' => 'nav-item']])
               ->setLinkAttribute('class', 'nav-link text-white')
               ->setLinkAttribute('style', 'background-color: #28a745; border-radius: 5px; margin: 5px 0; padding: 10px;')
               ->setExtra('icon', 'fa fa-folder mr-2');

           return $menu;
       }
   }