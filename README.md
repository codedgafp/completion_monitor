# completion_monitor

## Minifier les script JavaScripts

Pour que Moodle puisse lire et exécuter les JavaScripts, il faut qu'ils soient minifié.

Si c'est la première fois que vous initiez le plugin, ou si vous avez modifié / ajouté un fichier JavaScript, il est essentiel de lancer la commande de
minification :

```
docker compose run --rm amd-minify
```

## Troubleshoot

En cas d'erreur avec <UNABLE_TO_GET_ISSUER_CERT_LOCALLY>, changer l'image docker.
