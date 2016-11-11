<?php

namespace FS\SolrBundle\Tests\Doctrine\Mapper;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use FS\SolrBundle\Doctrine\Annotation\AnnotationReader;
use FS\SolrBundle\Doctrine\Hydration\DoctrineHydrator;
use FS\SolrBundle\Doctrine\Hydration\HydrationModes;
use FS\SolrBundle\Doctrine\Hydration\IndexHydrator;
use FS\SolrBundle\Doctrine\Hydration\NoDatabaseValueHydrator;
use FS\SolrBundle\Doctrine\Hydration\ValueHydrator;
use FS\SolrBundle\Doctrine\Mapper\EntityMapper;
use FS\SolrBundle\Doctrine\Mapper\Mapping\MapAllFieldsCommand;
use FS\SolrBundle\Doctrine\Mapper\MetaInformationFactory;
use FS\SolrBundle\Tests\Util\MetaTestInformationFactory;
use Solarium\QueryType\Update\Query\Document\Document;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 *
 * @group mapper
 */
class EntityMapperTest extends \PHPUnit_Framework_TestCase
{

    private $doctrineHydrator = null;
    private $indexHydrator = null;
    private $metaInformationFactory;

    public function setUp()
    {
        $this->doctrineHydrator = $this->getMock('FS\SolrBundle\Doctrine\Hydration\HydratorInterface');
        $this->indexHydrator = $this->getMock('FS\SolrBundle\Doctrine\Hydration\HydratorInterface');
        $this->metaInformationFactory = new MetaInformationFactory(new AnnotationReader(new \Doctrine\Common\Annotations\AnnotationReader()));
    }

    public function testToDocument_EntityMayNotIndexed()
    {
        $mapper = new \FS\SolrBundle\Doctrine\Mapper\EntityMapper($this->doctrineHydrator, $this->indexHydrator, $this->metaInformationFactory);

        $actual = $mapper->toDocument(MetaTestInformationFactory::getMetaInformation());
        $this->assertNull($actual);
    }

    public function testToDocument_DocumentIsUpdated()
    {
        $reader = new AnnotationReader(new \Doctrine\Common\Annotations\AnnotationReader());

        $mapper = new \FS\SolrBundle\Doctrine\Mapper\EntityMapper($this->doctrineHydrator, $this->indexHydrator, $this->metaInformationFactory);
        $mapper->setMappingCommand(new MapAllFieldsCommand(new MetaInformationFactory($reader)));

        $actual = $mapper->toDocument(MetaTestInformationFactory::getMetaInformation());
        $this->assertTrue($actual instanceof Document);

        $this->assertNotNull($actual->id);
    }

    public function testToEntity_WithDocumentStub_HydrateIndexOnly()
    {
        $targetEntity = new ValidTestEntity();

        $this->indexHydrator->expects($this->once())
            ->method('hydrate')
            ->will($this->returnValue($targetEntity));

        $this->doctrineHydrator->expects($this->never())
            ->method('hydrate');

        $mapper = new EntityMapper($this->doctrineHydrator, $this->indexHydrator, $this->metaInformationFactory);
        $mapper->setHydrationMode(HydrationModes::HYDRATE_INDEX);
        $entity = $mapper->toEntity(new SolrDocumentStub(), $targetEntity);

        $this->assertTrue($entity instanceof $targetEntity);
    }

    public function testToEntity_ConcreteDocumentClass_WithDoctrine()
    {
        $targetEntity = new ValidTestEntity();
        $targetEntity->setField('a value');

        $this->indexHydrator = new IndexHydrator(new NoDatabaseValueHydrator());

        $this->doctrineHydrator = new DoctrineHydrator($this->setupDoctrineRegistry($targetEntity, 1), new ValueHydrator());

        $mapper = new EntityMapper($this->doctrineHydrator, $this->indexHydrator, $this->metaInformationFactory);
        $mapper->setHydrationMode(HydrationModes::HYDRATE_DOCTRINE);
        $entity = $mapper->toEntity(new Document(array('id' => 'document_1', 'title' => 'value from index')), $targetEntity);

        $this->assertTrue($entity instanceof $targetEntity);

        $this->assertEquals('a value', $entity->getField());
        $this->assertEquals('value from index', $entity->getTitle());
    }

    private function setupDoctrineRegistry($entity, $expectedEntityId)
    {
        $repository = $this->getMock(ObjectRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($expectedEntityId)
            ->will($this->returnValue($entity));

        $manager = $this->getMock(ObjectManager::class);
        $manager->expects($this->once())
            ->method('getRepository')
            ->will($this->returnValue($repository));

        $registry = $this->getMock(RegistryInterface::class);
        $registry->expects($this->once())
            ->method('getManager')
            ->will($this->returnValue($manager));

        return $registry;
    }
}

