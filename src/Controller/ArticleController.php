<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Article;
use App\Form\ArticleFormType;
use Doctrine\ORM\EntityManagerInterface;
use SebastianBergmann\Environment\Console;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;


class ArticleController extends AbstractController
{

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'article_index')]
    public function index(): Response
    {
        

        // Récupérer toutes les données de la table
        $donnees = $this->entityManager->getRepository(Article::class)->findAll();
        
        return $this->render('article/index.html.twig', [
            'donnees' => $donnees
        ]);
    }

    #[Route('/detail/{id}', name: 'article_show')]
    public function show($id): Response
    {   
        // Récupérer une donnée de la table
        $donnees = $this->entityManager->getRepository(Article::class)->find($id);
        
        return $this->render('article/show.html.twig', [
            'donnees' => $donnees,
        ]);
    }

    #[Route('/sell', name: 'article_new')]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        $article = new Article();
        $form = $this->createForm(ArticleFormType::class, $article);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $article->setDatePublication(new \DateTime());
            $user = $this->getUser();
            if ($user) {
                $userId = $user->getId();
                $article->setCreatorId($userId);
            }
            $image = $form->get('image')->getData();

            if ($image) {
                $originalFilename = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);

                $safeFilename = $slugger->slug($originalFilename);
                
                $newFilename = $safeFilename.'-'.uniqid().'.'.$image->guessExtension();

                try {
                    $image->move(
                        $this->getParameter('images_directory'),
                        $newFilename
                    );
                } catch (FileException $e) {
                    print_r($e);
                }

                $article->setImage($newFilename);
            }

            $entityManager->persist($article);
            $entityManager->flush();

            return $this->redirectToRoute('article_index');
        }

        return $this->render('article/newSell.html.twig', [
            'articleForm' => $form,
        ]);
    }
}
