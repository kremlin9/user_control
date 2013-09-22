<?php

namespace User\GeneralBundle\Controller;

# General
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

# Entity
use User\GeneralBundle\Entity\User;
use User\GeneralBundle\Entity\LastVisit;
use User\GeneralBundle\Entity\BonusLastVisit;

class DefaultController extends Controller {

    public function getConfig() {
        $rep = $this->getDoctrine()
            ->getRepository('UserGeneralBundle:Config');
        
        $conf = $rep->findAll();

        $config = array();
        foreach ($conf as $c) {
            $config[ $c->getName() ] = $c->getValue();
        }

        return $config;
    }


    public function lookupUserByUid($id) {
        $rep = $this->getDoctrine()->getRepository('UserGeneralBundle:User');
        $q = $rep->createQueryBuilder('p')
            ->where('p.uid = :uid')
            ->setParameters(array(
                'uid' => $id
            ))
            ->getQuery();

        return $q->getResult();
    }

    public function getUserAction(Request $r) {
        $uid = $r->get('uid');

        $user = $this->lookupUserByUid($uid);

        if (!$user) {
            $answer = array( 'error' => 'user not found' );
            $response = new Response(json_encode($answer));
            return $response;
        } else {
            $user = $user[0];

            $serializer = $this->get('serializer');
            $json = $serializer->serialize($user, 'json');
            $response = new Response($json);
            return $response;
        }
    }

    public function registerUserAction(Request $r) {
        $user = $this->lookupUserByUid($r->get('uid'));

        if ($user) {
            $answer = array( 'error' => 'user exists' );
            $response = new Response(json_encode($answer));
            return $response;
        }

        $email_arr = array();
        preg_match("/my.mail.ru\/(\S+)\/(\S+)\//", $r->get('link'), $email_arr);
        $box   = $email_arr[1];
        $login = $email_arr[2];
        $email = '';

        if ('corp' == $box) {
            $email = "$login@corp.mail.ru"; 
        } else {
            $email = "$login@$box.ru"; 
        }

        $name = $r->get('first_name').' '.$r->get('last_name');
        if (strlen($name) < 3) {
            $name = $r->get('nick');
        }

        # Register new User 
        $user = new User;

        $user->setUid( $r->get('uid') );

        $user->setGender( $r->get('sex') );
        $user->setBirthday( $r->get('birthday') );

        $user->setFirstName( $r->get('first_name') );
        $user->setLastName( $r->get('last_name') );
        $user->setNick( $r->get('nick') );

        $user->setEmail( $email );
        $user->setBox( $box );
        $user->setLogin( $login );
        $user->setLink( $r->get('link') );

        $user->setRefType( $r->get('referer_type') );
        $user->setRefId( $r->get('referer_id') );

        $location = $r->get('location');

        if (isset($location)) {

            if (isset($location['country'])) {
                $user->setCountryId( $location['country']['id'] );
                $user->setCountryName( $location['country']['name'] );
            }

            if (isset($location['city'])) {
                $user->setCityId( $location['city']['id'] );
                $user->setCityName( $location['city']['name'] );
            }

            if (isset($location['region'])) {
                $user->setRegionId( $location['region']['id'] );
                $user->setRegionName( $location['region']['name'] );
            }
        }

        # Default balance value for newbie
        $config = $this->getConfig();         
        $user->setBalance($config['start_bonus']); 
        
        $em = $this->getDoctrine()->getManager();
        $em->persist($user);
        $em->flush();

        $serializer = $this->get('serializer');
        $json = $serializer->serialize($user, 'json');
        $response = new Response($json);
        return $response;

        $answer = array( 'error' => 'user not found' );
        $response = new Response(json_encode($answer));
        return $response;
    }

    public function changeBalanceAction(Request $r) {
        $uid = $r->get('uid');
        $balance = $r->get('balance') ? $r->get('balance') : 0;

        $user = $this->lookupUserByUid($uid);

        if (!$user) {
            $answer = array( 'error' => 'user not found' );
            $response = new Response(json_encode($answer));
            return $response;

        } else {
            $user = $user[0];

            $user->setBalance($balance); 

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();

            $serializer = $this->get('serializer');
            $json = $serializer->serialize($user, 'json');
            $response = new Response($json);
            return $response;
        }
    }

    public function getLastVisitAction(Request $r) {
        $uid = $r->get('uid');

        $now = new \DateTime('NOW'); 
        $last_visit = '';

        # Get Last visit time
        $rep   = $this->getDoctrine()->getRepository('UserGeneralBundle:LastVisit');
        $visit = $rep->createQueryBuilder('p')
            ->where('p.uid = :uid')
            ->setParameters(array(
                'uid' => $uid
            ))
            ->getQuery()
            ->getResult();

        if (count($visit) > 0) {
            $visit = $visit[0];
            $last_visit = $visit->getUpdatedAt();

            $visit->setUpdatedAt( $now );

        } else {
            $last_visit = new \DateTime("2013-09-19 00:00:00"); 

            $visit = new LastVisit;
            $visit->setUid($uid);
            $visit->setUpdatedAt( $now );
        }

        # Update it
        $em = $this->getDoctrine()->getManager();
        $em->persist($visit);
        $em->flush();

        $answer = array( 'last_visit' => $last_visit );
        $response = new Response(json_encode($answer));
        return $response;
    }


    public function getBonusLastVisitAction(Request $r) {
        $uid = $r->get('uid');

        $last_visit = '';

        # Get Last visit time
        $rep   = $this->getDoctrine()->getRepository('UserGeneralBundle:BonusLastVisit');
        $visit = $rep->createQueryBuilder('p')
            ->where('p.uid = :uid')
            ->setParameters(array(
                'uid' => $uid
            ))
            ->getQuery()
            ->getResult();

        if (count($visit) > 0) {
            $visit = $visit[0];
            $last_visit = $visit->getUpdatedAt();
        } else {
            $last_visit = new \DateTime("2013-09-19 00:00:00"); 

            $visit = new BonusLastVisit;
            $visit->setUid($uid);
            $visit->setUpdatedAt( $last_visit );
        }

        # Update it
        $em = $this->getDoctrine()->getManager();
        $em->persist($visit);
        $em->flush();

        $answer = array( 'last_visit' => $last_visit->format("U") );
        $response = new Response(json_encode($answer));
        return $response;
    }

    public function updateBonusLastVisitAction(Request $r) {
        $uid = $r->get('uid');

        # Get Last visit time
        $rep   = $this->getDoctrine()->getRepository('UserGeneralBundle:BonusLastVisit');
        $visit = $rep->createQueryBuilder('p')
            ->where('p.uid = :uid')
            ->setParameters(array(
                'uid' => $uid
            ))
            ->getQuery()
            ->getResult();

        if (count($visit) > 0) {
            $now = new \DateTime('NOW'); 

            $visit = $visit[0];
            $visit->setUpdatedAt( $now );

            $em = $this->getDoctrine()->getManager();
            $em->persist($visit);
            $em->flush();

            $answer = array( 'done' => 'last time updated' );
            $response = new Response(json_encode($answer));
            return $response;
        } else {
            $answer = array( 'error' => 'user not found' );
            $response = new Response(json_encode($answer));
            return $response;
        }
    }
}
