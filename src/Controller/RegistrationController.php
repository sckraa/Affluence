<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Security\LogginFormAuthenticator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Guard\GuardAuthenticatorHandler;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    /**
     * @Route("/register", name="app_register")
     */
    public function register(Request $request, UserPasswordEncoderInterface $passwordEncoder, GuardAuthenticatorHandler $guardHandler, LogginFormAuthenticator $authenticator): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // encode the plain password
            $user->setPassword(
                $passwordEncoder->encodePassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setRoles(["ROLE_USER"]);
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($user);
            $entityManager->flush();
            // do anything else you need here, like send an email

            return $guardHandler->authenticateUserAndHandleSuccess(
                $user,
                $request,
                $authenticator,
                'main' // firewall name in security.yaml
            );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/user/register", name="api_register", methods={"POST"})
     */
    public function apiRegister(Request $request, UserPasswordEncoderInterface $passwordEncoder, SerializerInterface $serializer, ValidatorInterface $validator, UserRepository $userRepository) {
         $userInterface = new User();
        $jsonRequest = $request->getContent();
        try {
            $user = $serializer->deserialize($jsonRequest, User::class, 'json');
            $pseudo = $user->getPseudo();
            $userTest = $userRepository->findBy(["pseudo" => $pseudo]);
            if($userTest) {
                return $this->json("Ce pseudo est déjà utilisé", 409, ['Access-Control-Allow-Origin' => '*', "Content-Type" => "application/json"]);
            }
            $email = $user->getEmail();
            $userTest = $userRepository->findBy(["email" => $email]);
            if($userTest) {
                return $this->json("Cet email est déjà utilisé.", 409, ['Access-Control-Allow-Origin' => '*', "Content-Type" => "application/json"]);
            }
            $password = $user->getPassword();
            if(isset($password)) {
                if (!preg_match('^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[-+!*$@%_])([-+!*$@%_\w]{6,})$^', $password)) {
                    return $this->json("Le mot de passe n'est pas au bon format.", 415, ['Access-Control-Allow-Origin' => '*', "Content-Type" => "application/json"]);
                }
            }
            else {
                return $this->json("Le mot de passe n'est pas fourni.", 415, ['Access-Control-Allow-Origin' => '*', "Content-Type" => "application/json"]);
            }
            $errors = $validator->validate($user);
            if(count($errors) > 0) {
                return $this->json($errors, 400, ['Access-Control-Allow-Origin' => '*', "Content-Type" => "application/json"]);
            }

            // encode the plain password
            $user->setPassword(
                $passwordEncoder->encodePassword(
                    $userInterface,
                    $user->getPassword()
                )
            );
            $user->setRoles(["ROLE_USER"]);
            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
            return $this->json("Inscription réussi.", 201, ["Access-Control-Allow-Origin" => "*", "Content-Type" => "application/json"]);
        } catch (NotEncodableValueException $e) {
            return $this->json([
                'status' => 400,
                'message' => $e->getMessage(),
            ], 400, ["Access-Control-Allow-Origin" => "*", "Content-Type" => "application/json"]);
        }
    }
}
