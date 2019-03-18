pipeline {
  agent any
  stages {
    stage('MakeDocker') {
      steps {
        echo 'Automation started'
      }
    }
    stage('') {
      steps {
        junit(allowEmptyResults: true, testResults: 'true')
      }
    }
    stage('Chrome test') {
      parallel {
        stage('Chrome test') {
          steps {
            echo 'Chome'
          }
        }
        stage('Firefox test') {
          steps {
            echo 'Firefox work'
          }
        }
        stage('IE+') {
          steps {
            echo 'IE work'
          }
        }
      }
    }
    stage('Build') {
      steps {
        sh 'echo "Build complete"'
      }
    }
    stage('Deploy Dev') {
      parallel {
        stage('Deploy Dev') {
          steps {
            echo 'Deploy'
          }
        }
        stage('Unit test') {
          steps {
            echo 'Run tests'
          }
        }
      }
    }
    stage('Deploy Stag') {
      steps {
        echo 'Soon'
      }
    }
    stage('Deploy Production') {
      steps {
        echo 'Soon!'
      }
    }
  }
}