<?php

/*
* This file is part of the OrbitaleCmsBundle package.
*
* (c) Alexandre Rock Ancelet <alex@orbitale.io>
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/

namespace Orbitale\Bundle\CmsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @UniqueEntity("slug")
 * @ORM\HasLifecycleCallbacks()
 * @ORM\MappedSuperclass(repositoryClass="Orbitale\Bundle\CmsBundle\Repository\CategoryRepository")
 */
abstract class Category
{
    /**
     * @return int|string
     */
    abstract public function getId();

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     *
     * @Assert\Type("string")
     * @Assert\NotBlank()
     */
    protected $name;

    /**
     * @var string
     *
     * @ORM\Column(name="slug", type="string", length=255, unique=true)
     *
     * @Assert\Type("string")
     * @Assert\NotBlank()
     */
    protected $slug;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text", nullable=true)
     *
     * @Assert\Type("string")
     */
    protected $description;

    /**
     * @var bool
     *
     * @ORM\Column(name="enabled", type="boolean")
     *
     * @Assert\Type("bool")
     */
    protected $enabled = false;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="created_at", type="datetime")
     *
     * @Assert\Type(\DateTime::class)
     */
    protected $createdAt;

    /**
     * @var Category
     *
     * @Assert\Type(Category::class)
     */
    protected $parent;

    /**
     * @var Category[]|ArrayCollection
     */
    protected $children;

    /**
     * @var Page[]|ArrayCollection
     */
    protected $pages;

    public function __toString()
    {
        return $this->name;
    }

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->children  = new ArrayCollection();
        $this->pages     = new ArrayCollection();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): Category
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description = null): Category
    {
        $this->description = $description;

        return $this;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug = null): Category
    {
        $this->slug = $slug;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled = false): Category
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(Category $parent = null): Category
    {
        if ($parent === $this) {
            // Refuse the category to have itself as parent.
            $this->parent = null;

            return $this;
        }

        $this->parent = $parent;

        // Ensure bidirectional relation is respected.
        if ($parent && false === $parent->getChildren()->indexOf($this)) {
            $parent->addChild($this);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $date): Category
    {
        $this->createdAt = $date;

        return $this;
    }

    /**
     * @return Category[]|ArrayCollection
     */
    public function getChildren()
    {
        return $this->children;
    }

    public function addChild(Category $category): Category
    {
        $this->children->add($category);

        if ($category->getParent() !== $this) {
            $category->setParent($this);
        }

        return $this;
    }

    public function removeChild(Category $child): Category
    {
        $this->children->removeElement($child);

        return $this;
    }

    /**
     * @return Category[]|ArrayCollection
     */
    public function getPages()
    {
        return $this->pages;
    }

    public function addPage(Page $page): Category
    {
        $this->children->add($page);

        if ($page->getCategory() !== $this) {
            $page->setCategory($this);
        }

        return $this;
    }

    public function removePage(Page $page): Category
    {
        $this->children->removeElement($page);

        $page->setCategory(null);

        return $this;
    }

    public function getTree(string $separator = '/'): string
    {
        $tree = '';

        $current = $this;
        do {
            $tree    = $current->getSlug().$separator.$tree;
            $current = $current->getParent();
        } while ($current);

        return trim($tree, $separator);
    }

    /**
     * @ORM\PrePersist()
     * @ORM\PreUpdate()
     */
    public function updateSlug(): void
    {
        if (!$this->slug) {
            $this->slug = mb_strtolower((new AsciiSlugger())->slug($this->name)->toString());
        }
    }

    /**
     * @ORM\PreRemove()
     *
     * @param LifecycleEventArgs $event
     */
    public function onRemove(LifecycleEventArgs $event): void
    {
        $em = $event->getEntityManager();
        if (count($this->children)) {
            foreach ($this->children as $child) {
                $child->setParent(null);
                $em->persist($child);
            }
        }
        $this->enabled = false;
        $this->parent  = null;
        $this->name .= '-'.$this->getId().'-deleted';
        $this->slug .= '-'.$this->getId().'-deleted';
    }
}
