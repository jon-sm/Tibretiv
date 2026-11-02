# jonsnmr/symfony-app:build - :base plus the CI/CD and dependency tooling.
FROM jonsnmr/symfony-app:base

USER root

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        composer \
        git \
        nodejs \
        unzip \
        yarnpkg \
    && rm -rf /var/lib/apt/lists/* \
    && ln -s /usr/bin/yarnpkg /usr/local/bin/yarn

USER app

CMD ["bash"]
