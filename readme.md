Nonlocking Replcopy
===============================================================================
![png](img/replcopy.png)

Nonlocking Replcopy��Transactd-Plugin���g�p����MySQL/MariaDB�p���v���P�[�V����
�Z�b�g�A�b�v�ƏC���R�s�[�𖳒�~�����b�N�Ȃ��ōs�����߂�PHP�X�N���v�g�ł��B

���̃X�N���v�g�ŁA�}�X�^�[����X���[�u�ւ̃f�[�^�̃R�s�[�ƃ��v���P�[�V�����̊J�n
�������s���܂��B���ɉғ����Ă��郌�v���P�[�V�����ł̕s��v�����̂��߂ɁA�ꕔ�̃e
�[�u����f�[�^�x�[�X�݂̂��C���R�s�[���邱�Ƃ��\�ł��B
����ɁA�Z�b�g�A�b�v�ƏC�����Ƀ}�X�^�[�̒�~��e�[�u�����b�N�͕s�v�ł��B�}�X�^�[
�̋@�\�𐧌����邱�ƂȂ��ғ������܂܏����ł��܂��B
MySQL / MariaDB�̗�����GTID�ɑΉ����Ă��܂��B


## �ڍ�
Replcopy�͏C���R�s�[�ɂ����āA�}�X�^�[�ƃX���[�u�ɖ����������Ȃ��悤��Transactd
��`nsdatabase::beginSnapshot()`API���g���܂��B���̃��\�b�h�̓o�C�i�����O�|�W�V��
���擾�Ɠ����ɃR�s�[�̂��߂̃X�i�b�v�V���b�g���J�n���܂��B
���̌�A�X���[�u�ɂ��̃|�W�V�������w�肵��`START SLAVE UNTIL`���s���đo���̃f�[
�^�𓯊���������ŁA�X���[�u���~���R�s�[���s���܂��B
����ɂ��MySQL�̃R�}���h�ł͎����ł��Ȃ��A���S�ȃe�[�u�����b�N�t���[�ɂ��}�X
�^�[�ƃX���[�u�̃f�[�^�������\�ɂ��Ă��܂��B


## �f��
���ۂɃX�N���v�g�����s�����Ƃ��̉�ʂł��B
`test_v3`�Ƃ������O�̃f�[�^�x�[�X���R�s�[���ă��v���P�[�V�������C�����Ă��܂��B
���s���ɏ����������v���P�[�V�����֌W�̃R�}���h�A�R�s�[�����f�[�^�x�[�X�ƃe�[�u��
���Ȃǂ��G�R�[����܂��B
�Ō��`Slave_IO_Running`��`Slave_SQL_Running`����������܂��B

```
[replcopy]# php dreplcpy.php ./repl_config_centOS.ini
Nonlocking replcopy version 1.0.0
--- Start replication setup  ---
Open slave database ...  done!
Stop slave ...  done!
Open master database ...  done!
Open master tables ...  done!
Begin snapshot on master ...  done!
Wait for stop slave until binlog pos ...  done!
Copying tables ...
  [database : test_v3]
    table : fieldtest ...  done!
    table : groups ...  done!
    table : nullkey ...  done!
    table : nullvalue ...  done!
    table : packrecord_test ...  done!
    table : scores ...  done!
    table : setenumbit ...  done!
    table : test ...  done!
    table : timetest ...  done!
    table : users ...  done!
Reset slave ...  done!
Change master ...
        set global gtid_purged='d30d02d6-3fe4-11e5-97db-00ffa4dbde57:1-8';
        change master to master_host='server1', master_port=3306, master_user='replication_user', master_password='123', master_auto_position = 1;  done!
Start slave ...  done!
Slave_IO_Running = Yes
Slave_SQL_Running = Yes
--- Replication setup has been completed ---
[replcopy]#
```


## ��ȓ���
* �}�X�^�[���~������e�[�u�����b�N�����肹���ɉғ������܂܁A�}�X�^�[/�X���[�u
  �ԂŖ������N�����Ȃ��Z�b�g�A�b�v���\
* �f�[�^�̃R�s�[�̓}�X�^�[����X���[�u�Ƀ_�C���N�g�ɓ]�����邽�߁A�ꎞ�t�@�C����
  �R�s�[�Ƃ��������삪�s�v
* Transactd API�ō����ȓǂݎ�菑�����݂��s�����߁Amysqldump�ɂ��_���v�ƃC��
  �|�[�g��荂���ɏ����ł���
* �R�s�[����f�[�^�́A�T�[�o�[�S�́A�����̑I�������f�[�^�x�[�X�A1�̃f�[�^�x�[
  �X�̑I�������e�[�u���A��3����I���ł���
* �C���R�s�[�̍ۂ�SQL�X���b�h�G���[�̃X�L�b�v�������Θb�I�ɍs����
* MySQL/MariaDB������GTID���v���P�[�V�����ɑΉ�
* �X�N���v�g�����s����z�X�g�̓}�X�^�[���[�J���E�X���[�u���[�J���E�����[�g������
  �ł���
* �r���[���R�s�[�ł���


## ���s��
* OS : Linux / Windows / Mac OSX
* PHP: 5.x / 7.0 �iWindows��PHP5��5.5�ȏ�j
* Transactd: �o�[�W���� 3.4.1�ȏ�i�T�[�o�[�v���O�C���E�N���C�A���g�̗����j
* MySQL 5.5�ȏ� / MariaDB 5.5�ȏ�
  �iMariaDB 10.0.8�`10.0.12�̓o�O�����邽�ߎg�p�s�j�iMySQL�N���C�A���g�͕s�v�j


## ��������
* InnoDB�̃e�[�u����Ώۂɂ��Ă��܂��BMyISAM�Ȃǔ�g�����U�N�V���i���ȃe�[�u����
  �R�s�[���Ƀ}�X�^�[�ɕύX������ƁA�X���[�u�Ƃ̊ԂŖ����������邱�Ƃ�����܂��B
* �T�[�o�[�S�̃R�s�[�ł�mysql�f�[�^�x�[�X�͏��O����܂��B�������Amysql�f�[�^�x�[
  �X�݂̂��w�肵���R�s�[�͉\�ł��B
* tables�p�����[�^�ɂ��e�[�u���w��ŁA�r���[�͎w��ł��܂���B


## �g����
```
php replcopy.php repl_config.ini
```
���v���P�[�V�����Ɋւ���ݒ�͂��ׂ�ini�t�@�C���ōs���܂��Breplcopy.php�ւ̈���
�͂���ini�t�@�C�����݂̂ł��B

`START SLAVE UNTIL`��SQL�X���b�h�ɃG���[������ꍇ�́A���̃G���[���X�L�b�v���邩
�ǂ�����₢���킹��v�����v�g���\������܂��B�I������Y/A/C��3�ł��B
* Y��I������ƍŌ��1�̃G���[�̂݃X�L�b�v����܂��B
* A�͂��ׂẴG���[���X�L�b�v���܂��B
* C�͂��̃X�N���v�g�̎��s���L�����Z�����܂��B

A�̂��ׂẴG���[���X�L�b�v�ł́A *`RESET SLAVE`�����s���ă����[���O�����ׂč폜
���܂��B���̏ꍇ�A�����[���O�ɃR�s�[�ΏۈȊO�̃e�[�u���̃g�����U�N�V�����������
����̓X���[�u�ɔ��f����܂���B���̂悤�ȃ��O������Ƒz�肳���ꍇ�́AA��I��
���Ȃ��ł��������B*


### repl_config.ini
#### �T���v��
```
[master]
host=server1:8610
repl_port=3306
repl_user=replication_user
repl_passwd=*

databases=test_v3
tables=
ignore_tables=

[slave]
host=server2:8610
master_resettable=1
log_bin=1

[gtid]
using_mysql_gtid=1
type=2
```

#### master �Z�N�V����
�}�X�^�[�T�[�o�[�̏���ݒ肵�܂��B`passwd`�܂���`repl_passwd`��`*`���w�肷��
�ƁA���s�����̓v�����v�g�Ŏw��ł��܂��B

* `host`          : �z�X�g���܂���IP�A�h���X + Transactd�|�[�g�ԍ�
  �i�� server1:8610�j
  �i�|�[�g���ȗ�����ƃf�t�H���g��8610�j
   �}�X�^�[�z�X�g�ɂ�localhost�͎w�肵�Ȃ����ƁB�X���[�u����A�N�Z�X�\�ȃz�X�g
   ���܂���IP�A�h���X�ł���K�v������B
* `user`          : Transactd�A�N�Z�X�̂��߂̃��[�U�[��
* `passwd`        : user�̃p�X���[�h
* `repl_port`     : MySQL�̃|�[�g�ԍ�
* `repl_user`     : ���v���P�[�V�������[�U�[��
* `repl_passwd`   : repl_user�̃p�X���[�h
* `databases`     : �R�s�[����f�[�^�x�[�X�B�J���}�ŋ�؂��ĕ����w��\ 
  �i�ȗ�����Ƃ��ׂẴf�[�^�ׁ[�X�j
* `tables`        : databases��1�̃f�[�^�x�[�X���w�肵���ꍇ�ɁA���̒��̃R�s�[
  ����e�[�u�����B�J���}�ŋ�؂��ĕ����w��\�i�ȗ�����Ƃ��ׂẴe�[�u���B
  �r���[�͎w��ł��Ȃ��B�j
* `ignore_tables` : �R�s�[���X�L�b�v����e�[�u�����܂��̓r���[���B�J���}�ŋ�؂�
  �ĕ����w��\�i�ȗ��\�j

#### slave �Z�N�V����
�X���[�u�T�[�o�[�̏���ݒ肵�܂��B`passwd`��`*`���w�肷��ƁA���s�����̓v����
�v�g�Ŏw��ł��܂��B

* `host`              : �z�X�g���܂���IP�A�h���X + Transactd�|�[�g�ԍ�
  �i�� server2:8611�j�i�|�[�g���ȗ�����ƃf�t�H���g��8610�j
* `user`              : Transactd�A�N�Z�X�ƃX���[�u�R���g���[���̂��߂�SUPER����
  �������[�U�[��
* `passwd`            : user�̃p�X���[�h
* `master_resettable` : 0��1�BMySQL��GTID���[�h�̂Ƃ��A�X���[�u��`RESET MASTER`
  ���s���Ă��悢���ǂ����B���̃X���[�u���}�X�^�[�ł�����ꍇ��0���w�肷��B 
  [gtid]�Z�N�V������`type=2`���w�肵���ꍇ�̂ݗL���B
* `log_bin`           : �X���[�u�̃o�C�i�����O���R�s�[���L���ɂ��邩�ǂ����B����
  �X���[�u���}�X�^�[�ł�����ꍇ��1���w�肷��B

#### GTID �Z�N�V����
���v���P�[�V������GTID�Ɋւ������ݒ肵�܂��B

* `using_mysql_gtid`  : �X���[�u������`gtid_mode=on`�Ń��v���P�[�V�������g�p����
  ���邩�ǂ����B`0`��GTID���g���Ă��Ȃ��B`1`�͎g�p���B���̃p�����[�^��MariaDB��
  �ꍇ�͖��������B
* `type`              : GTID���g�����|�W�V�������g�p���邩�ǂ����B
  `0`��GTID�ł̃|�W�V�����w�肵�Ȃ��B`1`��MariaDB��GTID�A`2`��MySQL��GTID�B


## �C���X�g�[��
�T�[�o�[�ɂ̓}�X�^�[�E�X���[�u�Ƃ�Transactd�v���O�C��3.4.1�ȏオ�K�v�ł��B�ȉ�
���_�E�����[�h�ƃC���X�g�[�����s���Ă��������B

* [Transactd download]
(http://www.bizstation.jp/al/transactd/download/index.html)

�܂��A�ȉ����Q�Ƃ��āA�X�N���v�g�����s����z�X�g��Transactd PHP�N���C�A���g
3.4.1�ȏ���C���X�g�[�����܂��B

* [Transactd �C���X�g�[���K�C�h for PHP]
(http://www.bizstation.jp/ja/transactd/documents/install_guide_php.html)

`php/dreplcpy.php`��PHP�����s�\�t�H���_�ɕۑ����܂��B

`repl_config.ini`��ҏW���ă��v���P�[�V�����̐ݒ���s���Ă��������B


## �o�O�񍐁E�v�]�E����Ȃ�
�o�O�񍐁E�v�]�E����Ȃǂ́A[github���Issue�g���b�J�[](
https://github.com/bizstation/dreplcopy/issues)�ɂ��񂹂��������B


## ���C�Z���X
GNU General Public License Version 2
```
   Copyright (C) 2016 BizStation Corp All rights reserved.

   This program is free software; you can redistribute it and/or
   modify it under the terms of the GNU General Public License
   as published by the Free Software Foundation; either version 2
   of the License, or (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software 
   Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  
   02111-1307, USA.
```
