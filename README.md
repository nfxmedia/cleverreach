#Cleverreach-Plugin for Shopware 4.x#


Nach der Installation finden Sie ein neues Menü-Item in Ihrem Shopware Shop unter der Rubrik Marketing: > CleverReach. Die nachfolgenden zwei Schritte, also 1. die Aktivierung des Plugins und 2. die Zuordnung einer CleverReach-Gruppe für mindestens eine Shopware Kundengruppe, sind obligatorisch als Minimalkonfiguration zur Nutzung Ihres CleverReach-Plugins. Bitte führen Sie anschließend den Erst-Export durch (Punkt 3) und informieren sich unter Punkt 4, 5 und 6 über die weiteren Möglichkeiten zur Einstellung Ihres Plugins.
Unter Marketing: > Cleverreach können Sie die Einstellungen Ihrer Cleverreach Accounts konfigurieren. Die neueste Version des Tools macht es nun möglich unterschiedliche Konfigurationen für ihre Subshops einzustellen. Es erscheint eine Übersicht in der alle Subshops, die Ihrer Shopware Installation zugeordnet sind, gelistet werden. Durch Anklicken des jeweiligen Shops lassen sich Details öffnen und die Konfiguration durchführen (rechte Seite des Backend Panels). 

**1. Aktivierung** 
API-Key: Zunächst benötigen Sie den "API-Schlüssel" (API-Key), um Ihr Plugin freizuschalten. Diesen Schlüssel erstellen Sie über Ihren CleverReach-Account und kopieren ihn in Ihr Shopware Backend: Dafür bitte unter https://eu.cleverreach.com einloggen und unter der Rubrik Accout > Extras > API einen API-Schlüssel erstellen. Den API-Schlüssel nun kopieren und in das Shopware-Backend Feld API-Key einfügen. 

 WSDL-URL: Das Feld “WSDL-Url” ist nach der Installation bereits ergänzt. Sobald der Button "Speichern und Status prüfen" geklickt wurde ist Ihr Plugin aktiv.

 Weiterhin könne Sie unter dem Feld “Status” das letzte Datum der Statusüberprüfung sehen; diese Überprüfung geschieht automatisch sobald das Fenster geöffnet wird oder sobald der Button "Speichern und Status prüfen" betätigt wurde. Alle weiteren Bereiche sind nun editierbar und können konfiguriert werden. 


![Abbildung: Backend Einstellungen im Überblick](/Frontend/CrswCleverReach/docs/Cleverreach_Backend_Einstellungen.png)
Abbildung: Backend Einstellungen im Überblick

**2. Gruppen Zuordnung**
Der nächste Schritt nach der Aktivierung des Tools ist es, CleverReach Gruppen den Shopware-Kundengruppen zuzuweisen. 

 Zunächst müssen die Gruppen unter https://eu.cleverreach.com definiert werden. Empfänger > Gruppen > Neue Gruppe anlegen. Genauer werden hier von Ihnen Gruppennamen erstellt. Es können so viele Gruppen wie gewünscht angelegt werden. Anmerkung: Dieser Schritt liegt vor dem ersten Export (Punkt 3), da die Zuordnungen des Exports auf diesem Schritt basieren.

 Zurück im Shopware Fenster, unter Shop/Kundengruppen-Listen-Zuordnung kann die Zuordnung stattfinden: Hier gibt es die Liste der Shops und für jeden Shop gibt es die Liste der Kundengruppen (wie Shopkunden, Amazon Kunden etc.).

 Durch den Doppelklick auf eine Kundengruppe lässt sie sich editieren und unter "Gruppen" kann nun eine vordefinierte Gruppe aus einer Liste ausgewählt werden (vordefiniert wurde sie zuvor unter eu.cleverreach.com).

 Von nun an werden neue Shopware Kunden, die sich für die Newsletter Abo-Option entscheiden zu CleverReach exportiert und den Kundengruppen damit zugeordnet. Die Newsletter Abo-Option ist über ein Feld im Registrierungsprozess, im Kunden-Account-Bereich und über das Newsletter-Abo-Feld aktivierbar. Diese (neuen) Kundendaten werden bei der Registrierung und zu jeder Bestellung, die ein Kunde tätigt, exportiert. 

 Nachdem eine Zuordnung getätigt wurde, wird die Rubrik > Shop/Kundengruppen-Listen-Zuordnung im Bereich Status-Information mit einem grünen Häkchen als erledigt markiert.

 Neben den bereits existierenden Shopware-Kundengruppen, enthält die Rubrik > Shop/Kundengruppen-Listen-Zuordnung zwei weitere spezifische Gruppen: Interessenten und Bestellkunden:

 Interessenten: hiermit werden die non-customers (Nicht-Einkaufs-Kunden), die ihren Newsletter über das Newsletter-Kontaktformular im Frontend abonniert haben, bezeichnet. In der Anwendung, können Sie somit das Mailing an Interessenten (Newsletter/oder andere Email-Informationen) mit einer anonymisierten Anrede verfassen, da die Kunden dieser Gruppe i. d. R. lediglich die Angabe zu Ihrer Email-Adresse getätigt haben.

 Bestellkunden: mit Bestellkunden sind diejenigen Kunden gemeint, die die Newsletter-Option unmarkiert ('unchecked') gelassen haben - sozusagen ‚reine‘ Bestell-Kunden. Auf der anderen Seite gibt es die Shop-Kunden, die die Newsletter-Checkbox aktiviert haben und damit der Gruppe Shopkunden zugeordnet werden.

**Mit diesen vorgenannten zwei Schritte, also der Aktivierung des Plugins und der Zuordnung einer CleverReach-Gruppe für mindestens eine Shopware Kundengruppe, haben Sie ihr Plugin bereits erfolgreich konfiguriert.**

**3. Erst-Export**
Für den Export von bestehenden (Shop-)Kundendaten zu CleverReach gehen Sie bitte auf den Export-Tab. Durch die Betätigung des Buttons “Start Export” werden die Kundendaten in einer Mehrfachverarbeitung (Batch) exportiert: “Export-Limit pro Step (max. 50):“ -  der Standardwert ist hier 50.


![Abbildung: Abbildung: Backend Erst-Export](/Frontend/CrswCleverReach/docs/Cleverreach_Backend_Erst_Export.png)
Abbildung: Backend Erst-Export

Die Kunden werden entsprechend der vorher festgelegten (in den vorgenannten Schritten) Zuordnungen exportiert. Der Exportstatus wird unter dem Bereich „Details“ angezeigt. Den erfolgreichen Export Ihrer Daten können Sie in der Status-Information unter der Rubrik Erst-Export einsehen: ein grünes Häkchen bestätigt den Export für den entsprechenden Shop.

**4. Opt-In Aktivierung**
Der Shopbesitzer kann das Opt-In Feature für jede Shopware Kundengruppe aktivieren, der einer CleverReach Gruppe zugeordnet wurde: das bedeutet in diesem Fall, dass die Kunden nicht automatisch/standardmäßig der Newsletter-Empfänger Gruppe zugeordnet werden. Sie werden weiterhin exportiert – doch ihr Status ist auf ‚inaktiv‘ gesetzt. Nach dem Export erhält der Kunde eine Opt-In-Email, so dass er entschieden kann, ob er sich für den Newsletter registrieren möchte.

 Dafür muss eine Formular-Vorlage unter https://eu.cleverreach.com  und Empfänger > Formulare definiert werden. Hier lässt sich eine große Auswahl von Vorlagen für die jeweiligen Gruppen anlegen.

![Abbildung: Formular-Vorlagen erstellen](/Frontend/CrswCleverReach/docs/Cleverreach_Formular_erstellen.png)
Abbildung: Formular-Vorlagen erstellen

 Im Anschluss daran steht Ihnen dann, zurück im Shopware Shop Fenster, innerhalb des Bereichs Shop/Kundengruppen-Listen-Zuordnung, die zuvor definierte Opt-In-Liste inklusive der Formular-Vorlagen für Ihre CleverReach Gruppe, zur Verfügung. Insofern können Sie nun eine Vorlage auswählen und das Opt-In-Feature für Ihre Kundengruppen aktivieren.

**5. Produkt-Suche aktivieren** 
Das Produkt-Suche - Feature kann für die Erstellung von Emails mit 'dynamischen Content' verwendet werden. Das Feature kann für einen spezifischen Shop durch einen Klick auf den Button unter “Produkt-Suche aktivieren” aktiviert werden (in der Rubrik Status-Information).  Entsprechend wird als Hinweis für die erfolgreiche Einrichtung, ein grünes Häkchen in der gleichen Rubrik unter Produkt-Suche zu sehen sein.

 Nachzuprüfen ist dies ebenfalls unter https://eu.cleverreach.com - Account > Integrationen/Plugins. Dort gibt es den Bereich Produkt-Suche Links und der neue Link wird angezeigt “Shopware - <Name des Shops>”. 


![Abbildung: Abbildung: Aktivierte Produkt-Suche; Shop hinzufügen](/Frontend/CrswCleverReach/docs/Cleverreach_Produkt_Suche.png)
Abbildung: Aktivierte Produkt-Suche; Shop hinzufügen

Sofern Sie ein neues Mailing erstellen wollen, der Dynamic-Content erhält, gehen Sie auf "+ Neues Element" in Ihrem E-Mail > Design-Editor: hier kann durch die vorgenannte Freigabe der Produkt-Suche zum Beispiel nach Ihren Shopware Produkten  gesucht werden. Das bedeutet im (technischen) Detail: CleverReach verbindet sich mit Shopware, empfängt die Details zu Artikeln und zeigt sie innerhalb der E-Mail-Vorlage an. Wie das Produkt in Ihrem Mailing dargestellt wird, also hinsichtlich des Layouts, bietet CleverReach Auswahlmöglichkeiten an. 


![Abbildung: Email-Entwurf / dynamischer Content in der Anwendung](/Frontend/CrswCleverReach/docs/Cleverreach_Email_Entwurf.png)
Abbildung: Email-Entwurf / dynamischer Content in der Anwendung

**6. Reset der Konfigurationen**
Alle vorgenommenen Konfigurationen können durch den Button “Reset” zurückgesetzt werden.





#Häufig gestellte Fragen (FAQ)#

**1. Was ist der Unterschied zwischen Bestellkunden und Shopkunden?**

Mit Bestellkunden sind diejenigen Kunden gemeint, die die Newsletter-Option unmarkiert ('unchecked') gelassen haben - sozusagen ‚reine‘ Bestell-Kunden. 
Mit Shopkunden wiederum sind diejenigen Kunden gemeint, die die Newsletter-Checkbox aktiviert haben. Diese Kunden können beispielsweise im Newsletter-Mailing mit einer persönlichen Anrede angesprochen werden, da diese Daten vorliegen.


**2. Was sind die häufigsten Gründe, dass meine Kundendaten nicht übertragen werden?**

Die häufigste Ursache für eine fehlerhafte / lückenhafte Übertragung von Kundendaten zwischen CleverReach und Shopware ist die fehlende Definition von Gruppen und damit der Zuordnung von Shopware-Daten zu CleverReach und umgekehrt. 
Bitte definieren Sie Kunden-Gruppen unter CleverReach und ordnen Sie diese den Shopware-Gruppen unter der Rubrik " Shop-Kundengruppen-Listen-Zuordnung" zu und führen Sie den "Erst-Export" erneut durch.


**3. Wie werden direkte Newsletter-Anmeldungen übertragen?**

Kunden, die sich für die direkte Newsletter-Anmeldung in Ihrem Shop entschieden haben, befinden sich in der Gruppe Interessenten.
Mit Interessenten werden eben diese "non-customers" (Nicht-Einkaufs-Kunden) bezeichnet, die den Newsletter über das Newsletter-Kontaktformular im Frontend abonniert haben: In der Anwendung, bedeutet das, dass Sie somit das Mailing an Interessenten (Newsletter/oder andere Mailings) mit einer anonymisierten Anrede verfassen können, da die Kunden dieser Gruppe i. d. R. lediglich die Angabe zu Ihrer Email-Adresse getätigt haben. Demgegenüber steht die Gruppe der Shopkunden, die die Newsletter-Checkbox über Ihr Kundenkonto aktiviert haben und von denen entsprechend (mehr) persönliche Daten vorliegen.