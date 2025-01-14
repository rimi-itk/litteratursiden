@Library('jenkins-pipeline')_

pipeline {
    agent any
    stages {
        stage('Docker') {
            agent {
                docker {
                    image 'itkdev/php7.2-fpm:latest' /* 7.2 is used as phan only runs with this version */
                    args '-v /var/lib/jenkins/.composer-cache:/.composer:rw'
                }
            }
            stages {
                stage('Build') {
                    steps {
                        sh 'composer install'
                    }
                }
            }
        }
        stage('Deployment') {
            parallel {
                stage('Litteratursiden - staging') {
                    when {
                        branch 'release'
                    }
                    steps {
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; git clean -d --force'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; git checkout ${BRANCH_NAME}'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; git fetch'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; git reset origin/${BRANCH_NAME} --hard'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; composer install  --no-dev'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; vendor/bin/drush updb -y'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; vendor/bin/drush entup -y'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; vendor/bin/drush config-import -y'"
                        sh "ansible devlitt -m shell -a 'cd /data/www/stg_litteratursiden_dk/htdocs; vendor/bin/drush cr'"
                    }
                }
                stage('Litteratursiden - production') {
                    when {
                        branch 'master'
                    }
                    steps {
                        timeout(time: 30, unit: 'MINUTES') {
                            input 'Should the site be deployed?'
                        }
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; git clean -d --force'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; git checkout ${BRANCH_NAME}'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; git fetch'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; git reset origin/${BRANCH_NAME} --hard'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; composer install --no-dev'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; vendor/bin/drush updb -y'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; vendor/bin/drush entup -y'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; vendor/bin/drush config-import -y'"
                        sh "ansible litt -m shell -a 'cd /data/www/litteratursiden_dk/htdocs; vendor/bin/drush cr'"
                    }
                }
            }
        }
    }
    post {
        always {
            script {
                slackNotifier(currentBuild.currentResult)
            }

            // Clean up our workspace
            cleanWs()
            deleteDir()
        }
    }
}
